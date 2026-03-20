#!/usr/bin/env python
"""Initialize NCE Theme Settings with default values and generate CSS file."""

import frappe
from frappe import _


def initialize_theme():
	"""Create or update NCE Theme Settings with defaults and generate CSS."""

	# Check if NCE Theme Settings exists
	if not frappe.db.exists("NCE Theme Settings", "NCE Theme Settings"):
		print("Creating NCE Theme Settings with default values...")

		# Create new document with defaults
		doc = frappe.new_doc("NCE Theme Settings")
		doc.theme_name = "Default"

		# Set all default colors (these match the DocType field defaults)
		doc.primary_color = "#3B82F6"
		doc.secondary_color = "#10B981"
		doc.accent_color = "#8B5CF6"
		doc.success_color = "#10B981"
		doc.info_color = "#3B82F6"
		doc.warning_color = "#F59E0B"
		doc.danger_color = "#EF4444"
		doc.text_color = "#1F2937"
		doc.heading_color = "#111827"
		doc.muted_color = "#6B7280"
		doc.link_color = "#3B82F6"
		doc.focus_color = "#3B82F6"
		doc.background_color = "#FFFFFF"
		doc.surface_color = "#F9FAFB"
		doc.border_color = "#E5E7EB"
		doc.row_alt_color = "#F3F4F6"
		doc.shadow_color = "#000000"

		# Set other defaults
		doc.font_family = "System Default"
		doc.heading_font_family = "System Default"
		doc.font_size = "14px"
		doc.line_height = "normal"
		doc.font_weight_body = "400"
		doc.border_radius = "md"
		doc.spacing_scale = "normal"
		doc.shadow = "md"
		doc.transition_speed = "normal"
		doc.sidebar_width = "240px"
		doc.container_max_width = "1200px"

		doc.flags.ignore_permissions = True
		doc.save()
		print("✓ NCE Theme Settings created with default values")
	else:
		print("NCE Theme Settings already exists, regenerating CSS...")
		doc = frappe.get_doc("NCE Theme Settings", "NCE Theme Settings")

	# Trigger CSS generation (this calls on_update which generates and writes CSS)
	doc.on_update()
	print(f"✓ Theme CSS generated ({len(doc.compiled_css or '')} bytes)")

	# Verify CSS file was created
	import os

	app_path = frappe.get_app_path("nce_builder")
	css_file = os.path.join(app_path, "public", "css", "nce_theme.css")

	if os.path.exists(css_file):
		file_size = os.path.getsize(css_file)
		print(f"✓ CSS file created at: {css_file} ({file_size} bytes)")
	else:
		print(f"✗ Warning: CSS file not found at: {css_file}")

	# Clear cache to ensure CSS is loaded
	frappe.clear_cache()
	print("✓ Cache cleared")

	return doc


def execute():
	"""Execute the theme initialization."""
	try:
		doc = initialize_theme()
		frappe.db.commit()
		print("\n✅ Theme initialization complete!")
		print("The following CSS variables are now available site-wide:")
		print("  --nce-color-primary")
		print("  --nce-color-secondary")
		print("  --nce-color-accent")
		print("  --nce-color-success")
		print("  --nce-color-info")
		print("  --nce-color-warning")
		print("  --nce-color-danger")
		print("  --nce-color-text")
		print("  --nce-color-heading")
		print("  --nce-color-muted")
		print("  --nce-color-link")
		print("  --nce-color-focus")
		print("  --nce-color-bg")
		print("  --nce-color-surface")
		print("  --nce-color-border")
		print("  --nce-color-row-alt")
		print("  --nce-shadow-color")
		print("\nUse these in any CSS or Vue component with var(--nce-color-primary) etc.")
		return doc
	except Exception as e:
		print(f"\n❌ Error during theme initialization: {str(e)}")
		frappe.db.rollback()
		raise


if __name__ == "__main__":
	# Allow running directly
	import os
	import sys

	# Add parent directory to path to import frappe
	sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

	frappe.init(site=os.environ.get("SITE") or frappe.local.site)
	frappe.connect()

	execute()

	frappe.destroy()
