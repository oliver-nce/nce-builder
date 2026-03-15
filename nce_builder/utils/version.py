import frappe


def get_frappe_major_version():
	"""Return the major version number of the installed Frappe framework."""
	try:
		return int(frappe.__version__.split(".")[0])
	except (ValueError, IndexError, AttributeError):
		return 15


def is_v16_or_later():
	"""Check if the running Frappe version is 16 or later."""
	return get_frappe_major_version() >= 16
