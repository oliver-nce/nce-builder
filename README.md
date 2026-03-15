# NCE Builder

Frappe v15/v16 custom app for site-wide theming and dynamic form rendering using FormKit + Frappe UI.

## Features

- **PathFinder** — Miller column navigator for DocType field relationships with 5 output modes (Jinja Tag, Text Block, Field Path, Related List, Button Action)
- **Site-wide theming** — FormKit Themes + Tailwind CSS variable sets stored in a DocType (planned)
- **Dynamic form rendering** — FormKit JSON schema forms targeting Frappe DocTypes (planned)

## Install

```bash
bench get-app https://github.com/oliver-nce/nce-builder.git
bench --site your-site install-app nce_builder
```

## License

MIT
