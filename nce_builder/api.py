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
	"""Resolve field values by walking link chains with full-row SQL loads.

	Loads entire rows (SELECT *) at each hop, caches by chain prefix so
	shared paths are only fetched once.  Returns per-element values AND
	the full loaded rows for button scripts or off-screen fields.
	"""
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
