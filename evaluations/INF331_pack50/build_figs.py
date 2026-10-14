import sys, importlib
from common import *
mods = sys.argv[1:] or ['figs_mcq', 'figs_open', 'figs_code']
only = [m.split('=')[1] for m in mods if m.startswith('only=')]
mods = [m for m in mods if not m.startswith('only=')] or ['figs_mcq', 'figs_open', 'figs_code']
for m in mods:
    try: mod = importlib.import_module(m)
    except ModuleNotFoundError: continue
    for name, fn in getattr(mod, 'DIAGRAMS', {}).items():
        if only and name not in only: continue
        render_fig(name, fn()); print('fig', name)
    for name, spec in getattr(mod, 'CODES', {}).items():
        if only and name not in only: continue
        render_code(name, spec['code'], spec.get('lang', 'Java'), spec.get('width', 15.5), spec.get('title')); print('code', name)
