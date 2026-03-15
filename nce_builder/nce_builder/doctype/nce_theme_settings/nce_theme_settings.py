import json
import os

import frappe
from frappe.model.document import Document


BORDER_RADIUS_MAP = {
	"none": "0",
	"sm": "0.125rem",
	"md": "0.375rem",
	"lg": "0.5rem",
	"full": "9999px",
}

SPACING_SCALE_MAP = {
	"tight": "0.75rem",
	"normal": "1rem",
	"relaxed": "1.5rem",
}


class NCEThemeSettings(Document):
	def on_update(self):
		css = self._generate_css()
		self.db_set("compiled_css", css, update_modified=False)
		self._write_css_file(css)
		frappe.clear_cache()

	def _generate_css(self):
		lines = [":root {"]

		if self.primary_color:
			lines.append(f"\t--nce-color-primary: {self.primary_color};")

		if self.font_family:
			lines.append(f"\t--nce-font-family: '{self.font_family}', sans-serif;")

		radius = BORDER_RADIUS_MAP.get(self.border_radius or "md", "0.375rem")
		lines.append(f"\t--nce-border-radius: {radius};")

		spacing = SPACING_SCALE_MAP.get(self.spacing_scale or "normal", "1rem")
		lines.append(f"\t--nce-spacing-base: {spacing};")

		if self.tailwind_overrides:
			try:
				overrides = json.loads(self.tailwind_overrides)
				for key, value in overrides.items():
					lines.append(f"\t--nce-{key}: {value};")
			except (json.JSONDecodeError, TypeError):
				pass

		lines.append("}")

		if self.custom_css:
			lines.append("")
			lines.append(self.custom_css)

		return "\n".join(lines)

	def _write_css_file(self, css):
		app_path = frappe.get_app_path("nce_builder")
		css_dir = os.path.join(app_path, "public", "css")
		os.makedirs(css_dir, exist_ok=True)
		css_file = os.path.join(css_dir, "nce_theme.css")
		with open(css_file, "w") as f:
			f.write(css)
