app_name = "nce_builder"
app_title = "NCE Builder"
app_publisher = "Oliver Reid"
app_description = "NCE Builder — Site-wide theming and dynamic form rendering for Frappe"
app_email = "oliver_reid@me.com"
app_license = "mit"
app_logo_url = "/assets/nce_builder/images/logo.jpg"

# SPA route rules — Frappe serves the Vue app for /nce/* paths
website_route_rules = [
	{"from_route": "/nce/<path:app_path>", "to_route": "nce"},
]

add_to_apps_screen = [
	{
		"name": "nce_builder",
		"logo": "/assets/nce_builder/images/logo.jpg",
		"title": "NCE Builder",
		"route": "/app/nce-builder",
	}
]
