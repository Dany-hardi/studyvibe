# -*- coding: utf-8 -*-
"""Shared helpers for the INF331 pack of 50 questions: the TikZ UML toolkit of the first pack, plus code listings drawn as pictures."""
import os, sys, subprocess, tempfile, shutil
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, '..', 'INF331'))
from uml import *                      # noqa  (cls, GEN, REAL, COMP, AGG, DEP, NAV, PRE, POST ...)
from diagrams_behavior import head, life, msg, ARR, OPEN, SYNC   # noqa

def L(x): return f"\\draw[line,{x}]"

def render_fig(name, body, dpi=170):
    work = tempfile.mkdtemp(prefix="fig_")
    open(os.path.join(work, "d.tex"), "w").write(PRE + body + POST)
    r = subprocess.run(["pdflatex", "-interaction=nonstopmode", "-halt-on-error", "d.tex"], cwd=work, capture_output=True, text=True)
    if not os.path.exists(os.path.join(work, "d.pdf")):
        print(r.stdout[-2500:]); raise SystemExit("TeX failed for " + name)
    subprocess.run(["pdftoppm", "-r", str(dpi), "-png", "-singlefile", os.path.join(work, "d.pdf"), os.path.join(HERE, "images", name)], check=True)
    shutil.copy(os.path.join(work, "d.tex"), os.path.join(HERE, "diagrams", name + ".tex"))
    shutil.rmtree(work, ignore_errors=True)

CODE_PRE = r"""\documentclass[border=10pt]{standalone}
\usepackage[T1]{fontenc}\usepackage[utf8]{inputenc}
\usepackage{xcolor,listings}
\definecolor{ink}{HTML}{1E1B16}\definecolor{paper}{HTML}{FBF8F2}\definecolor{clay}{HTML}{B5482A}\definecolor{pine}{HTML}{24402F}\definecolor{gray}{HTML}{7A7467}
\lstdefinestyle{sv}{language=%s,basicstyle=\ttfamily\small\color{ink},keywordstyle=\color{clay}\bfseries,commentstyle=\color{gray}\itshape,
  stringstyle=\color{pine},numbers=left,numberstyle=\tiny\color{gray},numbersep=8pt,showstringspaces=false,tabsize=4,columns=fullflexible,
  breaklines=false,xleftmargin=1.4em,morekeywords={%s},literate={é}{{\'e}}1 {è}{{\`e}}1 {à}{{\`a}}1 {ç}{{\c c}}1}
\begin{document}
\pagecolor{paper}\begin{minipage}{%scm}\vspace{4pt}
%s
\lstset{style=sv}
\begin{lstlisting}
"""
CODE_POST = r"""\end{lstlisting}
\vspace{2pt}\end{minipage}
\end{document}
"""

def render_code(name, code, lang="Java", width=15.5, title=None, extra_kw="record,var,sealed,permits", dpi=170):
    work = tempfile.mkdtemp(prefix="code_")
    t = (r"\textbf{\small\color{gray}%s}\par\vspace{2pt}" % title) if title else ""
    tex = CODE_PRE % (lang, extra_kw, width, t) + code.strip("\n") + "\n" + CODE_POST
    open(os.path.join(work, "d.tex"), "w").write(tex)
    r = subprocess.run(["pdflatex", "-interaction=nonstopmode", "-halt-on-error", "d.tex"], cwd=work, capture_output=True, text=True)
    if not os.path.exists(os.path.join(work, "d.pdf")):
        print(r.stdout[-2500:]); raise SystemExit("TeX failed for " + name)
    subprocess.run(["pdftoppm", "-r", str(dpi), "-png", "-singlefile", os.path.join(work, "d.pdf"), os.path.join(HERE, "images", name)], check=True)
    shutil.copy(os.path.join(work, "d.tex"), os.path.join(HERE, "diagrams", name + ".tex"))
    shutil.rmtree(work, ignore_errors=True)
