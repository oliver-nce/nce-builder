import json
import re

import frappe
from frappe.model.document import Document

JSON_FIELDS = ("form_schema", "field_mapping", "tab_layout", "validation_rules")
FORM_NAME_RE = re.compile(r"^[a-z0-9][a-z0-9-]*$")


class NCEFormDefinition(Document):
	def validate(self):
		for field in JSON_FIELDS:
			value = self.get(field)
			if value:
				try:
					json.loads(value)
				except json.JSONDecodeError:
					frappe.throw(f"{self.meta.get_label(field)} must be valid JSON")

		self._validate_form_name()

	def _validate_form_name(self):
		if self.form_name and not FORM_NAME_RE.match(self.form_name):
			frappe.throw(
				"Form Name must be URL-safe: lowercase letters, numbers, "
				"and hyphens only, must start with a letter or number"
			)
