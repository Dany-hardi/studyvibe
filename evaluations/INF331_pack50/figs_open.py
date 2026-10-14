# -*- coding: utf-8 -*-
from common import *

DASH = "dashed,line width=0.8pt,-{Straight Barb[length=8pt,width=8pt]}"
TRI = "-{Triangle[open,length=10pt,width=10pt]}"

def godclass_fr():
    b = r"\node[cls,minimum width=6.4cm,anchor=center] (g) at (0,0) {\textbf{GestionnaireScolarité}\nodepart{two}- connexionBD : Connection\\- smtpHote : String\\- listeEtudiants : List\\- listeCours : List\\- seuilAdmission : double\nodepart{three}+ inscrire(e : Etudiant, c : Cours)\\+ calculerMoyenne(e : Etudiant) : double\\+ genererBulletin(e : Etudiant) : byte[]\\+ envoyerEmail(e : Etudiant, texte : String)\\+ exporterPDF(b : byte[]) : File\\+ sauvegarderEnBase(e : Etudiant)\\+ verifierPaiement(e : Etudiant) : boolean\\+ importerNotesCSV(f : File)\\+ planifierExamen(c : Cours, d : Date)};"+"\n"
    pts = [('Etudiant',-7.6,1.7),('Cours',-7.6,0.0),('Note',-7.6,-1.7),('PdfLib',7.6,2.1),('SmtpClient',7.6,0.7),('MySqlConnection',7.6,-0.7),('CsvParser',7.6,-2.1)]
    for i,(n,x,y) in enumerate(pts):
        b += rf"\node[box,minimum width=2.6cm] (n{i}) at ({x},{y}) {{{n}}};"+"\n"
        side = 'west' if x < 0 else 'east'
        b += rf"\draw[{DASH}] (g.{side} |- n{i}.{'east' if x<0 else 'west'}) -- (n{i}.{'east' if x<0 else 'west'});"+"\n"
    return b

def gitgraph_fr():
    def c(i,x,y,l,fill="soft"): return rf"\node[circle,draw=ink,line width=0.8pt,fill={fill},minimum size=0.9cm,inner sep=0pt] ({i}) at ({x},{y}) {{\footnotesize {l}}};"+"\n"
    b  = r"\node[anchor=west,font=\bfseries] at (-1.2,4.4) {origin/main};"+"\n"
    for i,(x,l) in enumerate([(0,'M1'),(3,'M2'),(6,'M3'),(9,'M4')]):
        b += c(f"m{i}",x,3.2,l)
    for i in range(3): b += rf"\draw[line,{ARR}] (m{i+1}.west)--(m{i}.east);"+"\n"
    b += r"\node[anchor=west,font=\bfseries,text=clay] at (10.3,-1.1) {origin/feature (après le \texttt{git push -{}-force} de Bob)};"+"\n"
    b += c('p1',12.5,0.0,"B1'","clay!18")+c('p2',15.5,0.0,"B2'","clay!18")
    b += rf"\draw[line,{ARR}] (p1.north west)--(m3.south east);"+"\n"+rf"\draw[line,{ARR}] (p2.west)--(p1.east);"+"\n"
    b += r"\node[anchor=west,font=\bfseries,text=pine] at (-1.2,-1.5) {feature locale d'Alice (avant le force-push)};"+"\n"
    xs=[(5.2,'B1'),(8.2,'B2'),(11.2,'A1'),(14.2,'A2')]
    for i,(x,l) in enumerate(xs): b += c(f"a{i}",x,-3.0,l,"pine!14")
    b += rf"\draw[line,{ARR}] (a0.north west)--(m1.south east);"+"\n"
    for i in range(3): b += rf"\draw[line,{ARR}] (a{i+1}.west)--(a{i}.east);"+"\n"
    b += r"\node[note,anchor=west,text width=7.2cm] at (-1.2,-5.3) {B1', B2' : les commits B1 et B2 de Bob rejoués (rebase) sur M4. Alice a écrit A1 et A2 par-dessus B2 avant le force-push.};"+"\n"
    return b

def spec_remise_fr():
    t = (r"\textbf{Spécification de la fonction \texttt{net(m, fidèle)}}\\[3pt]"
         r"\textbf{R1} Le montant hors taxe $m$ est un décimal en FCFA. Toute valeur $m \le 0$ est rejetée\\ \hspace*{1.8em}avec l'erreur « montant invalide ».\\"
         r"\textbf{R2} $0 < m < 50\,000$ : remise de base de 0 \%.\\"
         r"\textbf{R3} $50\,000 \le m < 200\,000$ : remise de base de 5 \%.\\"
         r"\textbf{R4} $m \ge 200\,000$ : remise de base de 10 \%.\\"
         r"\textbf{R5} Un client fidèle reçoit 2 points de remise supplémentaires, mais la remise\\ \hspace*{1.8em}totale ne dépasse jamais 10 \%.\\"
         r"\textbf{R6} Le montant net $= m \times (1 - \mathrm{remise})$ est arrondi à l'entier le plus proche\\ \hspace*{1.8em}(0,5 arrondi vers le haut).")
    return rf"\node[note,anchor=north west,text width=13.4cm,font=\small] at (0,0) {{{t}}};"+"\n"

def pert_en():
    nodes = {'A':(0,2.6,3),'B':(0,-2.0,4),'C':(4.6,4.2,2),'D':(4.6,1.4,5),'E':(4.6,-2.0,3),'F':(9.2,2.8,4),'G':(9.2,-2.0,6),'H':(13.6,0.4,2),'I':(17.6,0.4,3)}
    b = ""
    for k,(x,y,d) in nodes.items():
        b += rf"\node[box,minimum width=2.4cm,minimum height=1.3cm] ({k}) at ({x},{y}) {{\textbf{{{k}}}\\ {d} days}};"+"\n"
    for p,q in (('A','C'),('A','D'),('B','E'),('C','F'),('D','F'),('E','G'),('F','H'),('G','H'),('H','I')):
        b += rf"\draw[line,{ARR}] ({p}.east)--({q}.west);"+"\n"
    b += r"\node[note,anchor=north west,text width=6.4cm] at (-1.2,-4.0) {Each box: activity and duration. An arrow $X \rightarrow Y$ means that $Y$ cannot start before $X$ is finished.};"+"\n"
    return b

def trace_en():
    mods = ['M1','M2','M3','M4','M5']; reqs = ['R1','R2','R3','R4','R5']
    data = {'T1':(['M1','M2'],['R1']),'T2':(['M2','M3'],['R1','R2']),'T3':(['M3'],['R2']),'T4':(['M3','M4'],['R3']),
            'T5':(['M4'],['R3']),'T6':(['M1'],['R1']),'T7':(['M5'],['R5']),'T8':(['M4','M5'],['R5'])}
    w=1.25; h=0.9; x0=1.4; y0=0
    b = r"\node[font=\bfseries] at (%f,1.2) {Modules exercised};" % (x0+2.5*w-0.5*w)
    b += r"\node[font=\bfseries] at (%f,1.2) {Requirements verified};" % (x0+(5+0.4)*w+2.5*w-0.5*w)
    cols = mods + [''] + reqs
    for j,cname in enumerate(cols):
        if cname: b += rf"\node[font=\bfseries] at ({x0+j*w},0.45) {{{cname}}};"+"\n"
    for i,(t,(ms,rs)) in enumerate(data.items()):
        y = -i*h-0.45
        b += rf"\node[font=\bfseries] at (0.3,{y}) {{{t}}};"+"\n"
        for j,cname in enumerate(cols):
            if cname in ms or cname in rs:
                b += rf"\node at ({x0+j*w},{y}) {{$\times$}};"+"\n"
    xr = x0+len(cols)*w-0.5*w-0.2
    for i in range(len(data)+1):
        yy = 0.0-i*h
        b += rf"\draw[line width=0.5pt] (-0.2,{yy+0.0}) -- ({xr},{yy+0.0});"+"\n"
    for j in range(len(cols)+1):
        xx = x0-0.5*w+j*w if j>0 else -0.2
        if j==6 : continue
    b += rf"\draw[line width=0.5pt] (-0.2,0)--(-0.2,{-len(data)*h});"+"\n"
    b += rf"\draw[line width=0.5pt] ({x0-0.5*w},0)--({x0-0.5*w},{-len(data)*h});"+"\n"
    b += rf"\draw[line width=0.9pt] ({x0+5*w-0.5*w+0.0},1.0)--({x0+5*w-0.5*w+0.0},{-len(data)*h});"+"\n"
    b += rf"\draw[line width=0.5pt] ({xr},0)--({xr},{-len(data)*h});"+"\n"
    return b

DIAGRAMS = dict(godclass_fr=godclass_fr, gitgraph_fr=gitgraph_fr, spec_remise_fr=spec_remise_fr, pert_en=pert_en, trace_en=trace_en)
