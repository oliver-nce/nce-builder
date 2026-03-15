from __future__ import annotations

import frappe


_DEFAULT_SYNTHETICS: list[dict[str, str]] = [
	{"field_name": "he_she", "label": "he/she", "male_value": "he", "female_value": "she"},
	{"field_name": "he_she_cap", "label": "He/She", "male_value": "He", "female_value": "She"},
	{"field_name": "him_her", "label": "him/her", "male_value": "him", "female_value": "her"},
	{"field_name": "his_her", "label": "His/Her", "male_value": "His", "female_value": "Her"},
	{"field_name": "his_her_lower", "label": "his/her", "male_value": "his", "female_value": "her"},
]


@frappe.whitelist()
def get_pronoun_tags_for_doctype(doctype: str) -> list[dict[str, str]]:
	"""Return pronoun tags for Tag Finder when the DocType has a gender field."""
	if not doctype or not doctype.strip():
		return []
	doctype = doctype.strip()
	try:
		meta = frappe.get_meta(doctype)
	except Exception:
		return []
	has_gender = any(
		f.fieldname and f.fieldname.lower() == "gender"
		for f in (meta.fields or [])
	)
	if not has_gender:
		return []
	pronoun_tags: list[dict[str, str]] = []
	for ds in _DEFAULT_SYNTHETICS:
		jinja = _compute_jinja_tag(
			ds["field_name"], ds["male_value"], ds["female_value"], "gender"
		)
		pronoun_tags.append({
			"field_name": ds["field_name"],
			"label": ds["label"],
			"jinja_tag": jinja,
		})
	return pronoun_tags


def _compute_jinja_tag(field_name: str, male_value: str, female_value: str, gender_field: str) -> str:
	male = (male_value or "").strip()
	female = (female_value or "").strip()
	if male or female:
		return (
			"{% if (gender|lower) == 'male' %}"
			+ (male or field_name)
			+ "{% else %}"
			+ (female or field_name)
			+ "{% endif %}"
		)
	return "{{ " + field_name + " }}"
