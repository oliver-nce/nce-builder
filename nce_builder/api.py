import frappe
import json


@frappe.whitelist()
def regenerate_theme_css():
	"""Regenerate the site-wide theme CSS from NCE Theme Settings."""
	doc = frappe.get_single("NCE Theme Settings")
	doc.on_update()
	return {"status": "ok", "compiled_css_length": len(doc.compiled_css or "")}


@frappe.whitelist()
def get_random_doc_name(doctype):
	"""Returns a single random document name from the given doctype."""
	names = frappe.get_all(doctype, limit_page_length=1, order_by='RAND()', pluck='name')
	return names[0] if names else None


@frappe.whitelist()
def resolve_fields(doctype, docname, fields_config):
	"""Resolve field values by walking link chains with full-row SQL loads."""
	if isinstance(fields_config, str):
		try:
			fields_config = json.loads(fields_config)
		except json.JSONDecodeError:
			return {"values": {}, "docs": {}, "docname": docname}

	if not isinstance(fields_config, list):
		return {"values": {}, "docs": {}, "docname": docname}

	root = frappe.db.get_value(doctype, docname, "*", as_dict=True)
	if not root:
		return {"values": {}, "docs": {}, "docname": docname}

	cache = {"": root}

	values = {}
	for item in fields_config:
		element_id = item.get("element_id")
		path = item.get("path", [])
		terminal_field = item.get("terminal_field")

		if not element_id:
			continue

		try:
			current = root
			chain_key = ""

			for hop in path:
				field = hop.get("field", "")
				chain_key = f"{chain_key}.{field}" if chain_key else field

				if chain_key in cache:
					current = cache[chain_key]
					if current is None:
						break
					continue

				link_value = current.get(field) if current else None
				if not link_value:
					cache[chain_key] = None
					current = None
					break

				row = frappe.db.get_value(hop.get("target", ""), link_value, "*", as_dict=True)
				cache[chain_key] = row
				current = row

			values[element_id] = current.get(terminal_field) if current and terminal_field else None
		except Exception:
			values[element_id] = None

	return {"values": values, "docs": cache, "docname": docname}


@frappe.whitelist()
def check_edit_lock(target_doctype, target_docname):
	"""Check if a record is currently locked for editing."""
	locks = frappe.get_all(
		"NCE Edit Lock",
		filters={"target_doctype": target_doctype, "target_docname": target_docname},
		fields=["name", "locked_by", "locked_at", "expires_at"],
		limit=1
	)

	if not locks:
		return {"locked": False}

	lock = locks[0]
	if frappe.utils.now_datetime() < frappe.utils.get_datetime(lock.expires_at):
		return {"locked": True, "locked_by": lock.locked_by, "locked_at": str(lock.locked_at)}

	frappe.delete_doc("NCE Edit Lock", lock.name, ignore_permissions=True)
	return {"locked": False}


@frappe.whitelist()
def acquire_edit_lock(target_doctype, target_docname, lock_minutes=15):
	"""Attempt to acquire an edit lock."""
	now = frappe.utils.now_datetime()
	current_user = frappe.session.user

	locks = frappe.get_all(
		"NCE Edit Lock",
		filters={"target_doctype": target_doctype, "target_docname": target_docname},
		fields=["name", "locked_by", "locked_at", "expires_at"],
		limit=1
	)

	if locks:
		lock = locks[0]
		if frappe.utils.now_datetime() < frappe.utils.get_datetime(lock.expires_at):
			if lock.locked_by == current_user:
				frappe.db.set_value("NCE Edit Lock", lock.name,
					"expires_at", frappe.utils.add_to_date(now, minutes=int(lock_minutes)))
				frappe.db.commit()
				return {"ok": True}
			return {"ok": False, "locked_by": lock.locked_by, "locked_at": str(lock.locked_at)}

	stale = frappe.get_all("NCE Edit Lock",
		filters={"target_doctype": target_doctype, "target_docname": target_docname},
		pluck="name")
	for name in stale:
		frappe.delete_doc("NCE Edit Lock", name, ignore_permissions=True)

	frappe.get_doc({
		"doctype": "NCE Edit Lock",
		"target_doctype": target_doctype,
		"target_docname": target_docname,
		"locked_by": current_user,
		"locked_at": now,
		"expires_at": frappe.utils.add_to_date(now, minutes=int(lock_minutes)),
	}).insert(ignore_permissions=True)
	frappe.db.commit()

	return {"ok": True}


@frappe.whitelist()
def release_edit_lock(target_doctype, target_docname):
	"""Release an edit lock held by the current user."""
	locks = frappe.get_all("NCE Edit Lock",
		filters={
			"target_doctype": target_doctype,
			"target_docname": target_docname,
			"locked_by": frappe.session.user,
		},
		pluck="name")

	for name in locks:
		frappe.delete_doc("NCE Edit Lock", name, ignore_permissions=True)

	frappe.db.commit()
	return {"ok": True}


@frappe.whitelist()
def save_resolved_fields(doctype, docname, updates):
	"""Write back edited fields through link chains with optimistic locking."""
	if isinstance(updates, str):
		try:
			updates = json.loads(updates)
		except json.JSONDecodeError:
			return {"ok": False, "conflicts": []}

	if not isinstance(updates, list):
		return {"ok": False, "conflicts": []}

	conflicts = []
	for update in updates:
		target_dt = update.get("target_doctype", "")
		target_name = update.get("target_name", "")
		expected_modified = update.get("modified", "")

		if not target_dt or not target_name:
			continue

		actual_modified = frappe.db.get_value(target_dt, target_name, "modified")
		if str(expected_modified) != str(actual_modified or ""):
			conflicts.append({
				"chain_key": update.get("chain_key", ""),
				"target_doctype": target_dt,
				"target_name": target_name,
				"expected": str(expected_modified),
				"actual": str(actual_modified or ""),
			})

	if conflicts:
		return {"ok": False, "conflicts": conflicts}

	for update in updates:
		target_dt = update.get("target_doctype", "")
		target_name = update.get("target_name", "")
		fields = update.get("fields", {})
		if target_dt and target_name and fields:
			frappe.db.set_value(target_dt, target_name, fields)

	_release_lock_internal(doctype, docname)
	frappe.db.commit()
	return {"ok": True}


def _release_lock_internal(target_doctype, target_docname):
	"""Release edit lock without requiring whitelist context."""
	locks = frappe.get_all("NCE Edit Lock",
		filters={
			"target_doctype": target_doctype,
			"target_docname": target_docname,
			"locked_by": frappe.session.user,
		},
		pluck="name")
	for name in locks:
		frappe.delete_doc("NCE Edit Lock", name, ignore_permissions=True)
