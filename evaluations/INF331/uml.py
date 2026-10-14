"""Small TikZ toolkit to draw UML-like figures (class, use case, state, activity, sequence, component, deployment) as PNG files."""
import os, subprocess, tempfile, shutil

PRE = r"""\documentclass[border=8pt]{standalone}
\usepackage[T1]{fontenc}\usepackage[utf8]{inputenc}
\usepackage{helvet}\renewcommand{\familydefault}{\sfdefault}
\usepackage{tikz,xcolor}
\usetikzlibrary{arrows.meta,positioning,shapes.geometric,shapes.multipart,calc,fit,backgrounds,decorations.markings}
\definecolor{ink}{HTML}{1E1B16}\definecolor{paper}{HTML}{FBF8F2}\definecolor{clay}{HTML}{B5482A}\definecolor{soft}{HTML}{F1E9DA}\definecolor{pine}{HTML}{24402F}
\tikzset{
  every node/.style={font=\small,text=ink},
  >=Stealth, line/.style={draw=ink,line width=0.8pt},
  cls/.style={rectangle split,rectangle split parts=3,draw=ink,line width=0.8pt,fill=soft,align=left,inner xsep=6pt,inner ysep=4pt,
              rectangle split part align={center,left,left},minimum width=3.0cm},
  cls2/.style={rectangle split,rectangle split parts=2,draw=ink,line width=0.8pt,fill=soft,align=left,inner xsep=6pt,inner ysep=4pt,
              rectangle split part align={center,left},minimum width=3.0cm},
  box/.style={rectangle,draw=ink,line width=0.8pt,fill=soft,align=center,inner sep=5pt,minimum height=0.8cm},
  state/.style={rectangle,rounded corners=8pt,draw=ink,line width=0.8pt,fill=soft,align=center,inner sep=6pt,minimum width=2.2cm,minimum height=0.9cm},
  act/.style={rectangle,rounded corners=10pt,draw=ink,line width=0.8pt,fill=soft,align=center,inner sep=5pt,minimum width=2.4cm,minimum height=0.8cm},
  dec/.style={diamond,draw=ink,line width=0.8pt,fill=soft,inner sep=1pt,minimum width=0.9cm,minimum height=0.9cm},
  uc/.style={ellipse,draw=ink,line width=0.8pt,fill=soft,align=center,inner sep=3pt,minimum width=3.2cm,minimum height=1.0cm},
  note/.style={rectangle,draw=ink,line width=0.6pt,fill=yellow!12,align=left,inner sep=4pt,font=\footnotesize},
  lab/.style={font=\footnotesize,fill=paper,inner sep=1.5pt},
  err/.style={},
}
\newcommand{\actor}[3]{% x,y,label
  \draw[line width=0.8pt] (#1,#2+0.55) circle (0.17);
  \draw[line width=0.8pt] (#1,#2+0.38)--(#1,#2-0.15) (#1-0.3,#2+0.22)--(#1+0.3,#2+0.22) (#1,#2-0.15)--(#1-0.25,#2-0.6) (#1,#2-0.15)--(#1+0.25,#2-0.6);
  \node[font=\small] at (#1,#2-0.9) {#3};}
\begin{document}
\begin{tikzpicture}[background rectangle/.style={fill=paper},show background rectangle]
"""
POST = r"""\end{tikzpicture}
\end{document}
"""

# arrow tips
GEN   = "-{Triangle[open,length=10pt,width=10pt]}"       # generalisation
REAL  = "dashed,-{Triangle[open,length=10pt,width=10pt]}" # realisation
COMP  = "{Diamond[length=12pt,width=8pt]}-"              # composition (filled)
AGG   = "{Diamond[open,length=12pt,width=8pt]}-"         # aggregation
DEP   = "dashed,-{Straight Barb[length=8pt,width=8pt]}"  # dependency
NAV   = "-{Straight Barb[length=8pt,width=8pt]}"         # navigable association

def cls(i, x, y, title, attrs=(), ops=(), width=None, stereo=None, abstract=False, parts=3):
    t = r"\textbf{%s}" % title
    if abstract: t = r"\textbf{\textit{%s}}" % title
    if stereo: t = r"{\footnotesize $\ll$%s$\gg$}\\ " % stereo + t
    style = "cls" if parts == 3 else "cls2"
    w = f",minimum width={width}cm" if width else ""
    if parts == 2 and not attrs and ops:
        attrs, ops = ops, ()
    a = r"\\".join(attrs) if attrs else r"\strut"
    o = r"\\".join(ops) if ops else r"\strut"
    body = f"{t}\\nodepart{{two}}{a}"
    if parts == 3: body += f"\\nodepart{{three}}{o}"
    return f"\\node[{style}{w}] ({i}) at ({x},{y}) {{{body}}};\n"

def render(name, body, outdir, dpi=170):
    work = tempfile.mkdtemp(prefix="uml_")
    tex = os.path.join(work, "d.tex")
    open(tex, "w").write(PRE + body + POST)
    r = subprocess.run(["pdflatex", "-interaction=nonstopmode", "-halt-on-error", "d.tex"], cwd=work, capture_output=True, text=True)
    if not os.path.exists(os.path.join(work, "d.pdf")):
        print(r.stdout[-1800:]); raise SystemExit("TeX failed for " + name)
    subprocess.run(["pdftoppm", "-r", str(dpi), "-png", "-singlefile", os.path.join(work, "d.pdf"), os.path.join(outdir, name)], check=True)
    shutil.copy(tex, os.path.join(os.path.dirname(outdir), "diagrams", name + ".tex"))
    shutil.rmtree(work, ignore_errors=True)
