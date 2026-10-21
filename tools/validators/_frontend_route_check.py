"""Check the shared server route contract without duplicating its component list."""
import re
from pathlib import Path


def supports_component(route: str, app: str, slug: str) -> bool:
    groups = re.findall(r"/components/\(\?P<component>([a-z|\-]+)\)", route)
    assets = (Path(__file__).resolve().parents[2] / "products/wordpress/plugins/axismundi/includes/assets.php").read_text(encoding="utf-8")
    return (
        len(groups) == 1
        and slug in groups[0].split("|")
        and "foreach ( axismundi_capstone_route_patterns() as $pattern => $route )" in route
        and "return ! empty( axismundi_capstone_resolve_public_path( $request ) );" in route
        and "window.axismundiCapstone?.resolvedRoute" in app
        and "$config['resolvedRoute'] = axismundi_capstone_resolve_public_path(" in assets
        and "stylebook\\/components" not in app
    )
