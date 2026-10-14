import sys, importlib
sys.path.insert(0, '.')
from uml import render
mods = [m for m in sys.argv[1:]] or ['diagrams_class', 'diagrams_behavior']
for m in mods:
    try:
        mod = importlib.import_module(m)
    except ModuleNotFoundError:
        continue
    for name, fn in mod.DIAGRAMS.items():
        render(name, fn(), 'images', dpi=170)
        print('ok', name)
