"""Independent contour checks for the generated Material loading assets."""
import copy
import json
import math
from pathlib import Path
import re
import sys
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/loading-indicators/assets"
NAMES = ["SoftBurst", "Cookie9Sided", "Pentagon", "Pill", "Sunny", "Cookie4Sided", "Oval"]


def contour(cubics):
    points = []
    for c in cubics:
        for i in range(17):
            t = i / 16
            u = 1 - t
            points.append(tuple(u**3*c[k] + 3*u*u*t*c[k+2] + 3*u*t*t*c[k+4] + t**3*c[k+6] for k in (0, 1)))
    return points


def distance(point, a, b):
    dx, dy = b[0] - a[0], b[1] - a[1]
    length = dx*dx + dy*dy
    t = max(0, min(1, ((point[0]-a[0])*dx + (point[1]-a[1])*dy) / length)) if length else 0
    return math.hypot(point[0]-a[0]-t*dx, point[1]-a[1]-t*dy)


def same_contour(a, b):
    # Original polygons and matched endpoints have different segmentation.
    for source, target in ((contour(a), contour(b)), (contour(b), contour(a))):
        edges = list(zip(target, target[1:] + target[:1]))
        if any(min(distance(p, x, y) for x, y in edges) > 0.001 for p in source):
            return False
    return True


def validate(data):
    failures = []
    count = 0

    def check(ok, label):
        nonlocal count
        count += 1
        if not ok:
            failures.append(label)

    check(data.get("viewBox") == [0, 0, 1, 1], "unit viewBox")
    check(data.get("precision") == 7, "coordinate precision")
    check([s["name"] for s in data.get("shapes", [])] == NAMES, "seven shapes in upstream order")
    check(len(data.get("pairs", [])) == 7, "seven cyclic morphs")
    shapes = {s["name"]: s["cubics"] for s in data["shapes"]}

    def curves_ok(curves, label):
        valid = bool(curves) and all(len(c) == 8 and all(isinstance(x, (int, float)) and math.isfinite(x) for x in c) for c in curves)
        check(valid, label + " finite cubic coordinates")
        if not valid:
            return False
        check(all(math.dist(c[-2:], curves[(i+1) % len(curves)][:2]) < 0.000002 for i, c in enumerate(curves)), label + " closed continuous contour")
        points = contour(curves)
        check(all(-0.02 <= x <= 1.02 and -0.02 <= y <= 1.02 for x, y in points), label + " bounds")
        check(all(math.hypot(x-.5, y-.5) <= .52 for x, y in points), label + " rotation bounds")
        area = abs(sum(a[0]*b[1]-b[0]*a[1] for a, b in zip(points, points[1:] + points[:1]))) / 2
        check(area > .03, label + " nonblank area")
        return True

    for name, curves in shapes.items():
        curves_ok(curves, name)
    for i, pair in enumerate(data["pairs"]):
        check(pair["from"] == NAMES[i] and pair["to"] == NAMES[(i+1) % 7], "cyclic pair " + str(i))
        check(len(pair["start"]) == len(pair["end"]), "matching topology " + str(i))
        valid = [curves_ok(pair[key], f"pair {i} {key}") for key in ("start", "end")]
        if all(valid):
            check(same_contour(pair["start"], shapes[pair["from"]]), "source contour " + str(i))
            check(same_contour(pair["end"], shapes[pair["to"]]), "destination contour " + str(i))
            for t in (.25, .5, .75):
                mid = [[x + (y-x)*t for x, y in zip(a, b)] for a, b in zip(pair["start"], pair["end"])]
                curves_ok(mid, f"pair {i} fraction {t}")
    return count, failures


def main():
    data = json.loads((ASSETS / "loading-geometry.json").read_text(encoding="utf-8"))
    count, failures = validate(data)
    for shape in data["shapes"]:
        svg = ET.parse(ASSETS / (shape["name"] + ".svg")).getroot()
        path = svg.find("{http://www.w3.org/2000/svg}path")
        numbers = [float(n) for n in re.findall(r"[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?", path.attrib["d"])]
        expected = shape["cubics"][0][:2] + [x for c in shape["cubics"] for x in c[2:]]
        count += 1
        if svg.attrib != {"viewBox": "0 0 1 1"} or numbers != expected:
            failures.append("static SVG differs: " + shape["name"])
    for file in ("LICENSE.md", "LICENSE-APACHE-2.0.txt"):
        count += 1
        if not (ASSETS / file).is_file():
            failures.append("missing " + file)
    if "--self-test" in sys.argv:
        for kind in ("coordinate", "topology", "loop"):
            changed = copy.deepcopy(data)
            if kind == "coordinate":
                changed["pairs"][0]["end"][0][2] = 10
            elif kind == "topology":
                changed["pairs"][0]["end"].pop()
            else:
                changed["pairs"][-1]["to"] = "Oval"
            count += 1
            if not validate(changed)[1]:
                failures.append("injected " + kind + " fault was not detected")
    print(f"loading geometry: {count} checks, {len(failures)} failed")
    for failure in failures:
        print("FAIL:", failure)
    return int(bool(failures))


if __name__ == "__main__":
    sys.exit(main())
