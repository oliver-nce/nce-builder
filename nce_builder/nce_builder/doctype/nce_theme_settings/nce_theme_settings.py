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

LINE_HEIGHT_MAP = {
	"tight": "1.25",
	"snug": "1.375",
	"normal": "1.5",
	"relaxed": "1.625",
	"loose": "2",
}

SHADOW_MAP = {
	"none": "none",
	"sm": "0 1px 2px 0 rgb(0 0 0 / 0.05)",
	"md": "0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)",
	"lg": "0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)",
	"xl": "0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)",
}

TRANSITION_MAP = {
	"fast": "150ms",
	"normal": "200ms",
	"slow": "300ms",
}

COLOR_FIELDS = {
	"primary_color": "color-primary",
	"secondary_color": "color-secondary",
	"accent_color": "color-accent",
	"success_color": "color-success",
	"info_color": "color-info",
	"warning_color": "color-warning",
	"danger_color": "color-danger",
	"text_color": "color-text",
	"heading_color": "color-heading",
	"muted_color": "color-muted",
	"link_color": "color-link",
	"focus_color": "color-focus",
	"background_color": "color-bg",
	"surface_color": "color-surface",
	"border_color": "color-border",
}


class NCEThemeSettings(Document):
	def on_update(self):
		css = self._generate_css()
		self.db_set("compiled_css", css, update_modified=False)
		self._write_css_file(css)
		frappe.clear_cache()

	def _generate_css(self):
		lines = [":root {"]

		for fieldname, var_name in COLOR_FIELDS.items():
			value = self.get(fieldname)
			if value:
				lines.append(f"\t--nce-{var_name}: {value};")

		if self.font_family and self.font_family != "System Default":
			lines.append(f"\t--nce-font-family: '{self.font_family}', sans-serif;")
		elif self.font_family == "System Default":
			lines.append(
				"\t--nce-font-family: -apple-system, BlinkMacSystemFont, "
				"'Segoe UI', Roboto, sans-serif;"
			)

		if self.heading_font_family and self.heading_font_family != "System Default":
			lines.append(f"\t--nce-font-heading: '{self.heading_font_family}', sans-serif;")
		elif self.heading_font_family == "System Default":
			lines.append(
				"\t--nce-font-heading: -apple-system, BlinkMacSystemFont, "
				"'Segoe UI', Roboto, sans-serif;"
			)

		if self.font_size:
			lines.append(f"\t--nce-font-size: {self.font_size};")

		if self.font_weight_body:
			lines.append(f"\t--nce-font-weight: {self.font_weight_body};")

		lh = LINE_HEIGHT_MAP.get(self.line_height or "normal", "1.5")
		lines.append(f"\t--nce-line-height: {lh};")

		radius = BORDER_RADIUS_MAP.get(self.border_radius or "md", "0.375rem")
		lines.append(f"\t--nce-border-radius: {radius};")

		spacing = SPACING_SCALE_MAP.get(self.spacing_scale or "normal", "1rem")
		lines.append(f"\t--nce-spacing-base: {spacing};")

		shadow = SHADOW_MAP.get(self.shadow or "md", SHADOW_MAP["md"])
		lines.append(f"\t--nce-shadow: {shadow};")

		transition = TRANSITION_MAP.get(self.transition_speed or "normal", "200ms")
		lines.append(f"\t--nce-transition-speed: {transition};")

		if self.sidebar_width:
			lines.append(f"\t--nce-sidebar-width: {self.sidebar_width};")

		if self.container_max_width:
			cw = "100%" if self.container_max_width == "full" else self.container_max_width
			lines.append(f"\t--nce-container-max-width: {cw};")

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
