from uml import *

def L(x): return f"\\draw[line,{x}]"
ARR = "-{Stealth[length=8pt,width=7pt]}"
OPEN = "-{Straight Barb[length=8pt,width=8pt]}"
SYNC = "-{Triangle[length=9pt,width=8pt]}"

def dab_en():
    b  = r"\draw[line] (2.0,-3.6) rectangle (12.4,3.8);"+"\n"
    b += r"\node[font=\bfseries] at (7.2,3.4) {ATM system};"+"\n"
    b += r"\actor{0.2}{0}{Customer}"+"\n"
    b += r"\node[uc] (w) at (5.0,0) {Withdraw cash};"+"\n"
    b += r"\node[uc] (a) at (9.6,1.9) {Authenticate};"+"\n"
    b += r"\node[uc] (p) at (9.6,-1.9) {Print receipt};"+"\n"
    b += r"\draw[line] (0.5,0.1)--(w.west);"+"\n"
    b += rf"\draw[dashed,line width=0.8pt,{OPEN}] (w.north east)--(a.west) node[midway,above,sloped,lab]{{«extend»}};"+"\n"
    b += rf"\draw[dashed,line width=0.8pt,{OPEN}] (w.south east)--(p.west) node[midway,below,sloped,lab]{{«include»}};"+"\n"
    b += r"\node[note,anchor=north] at (9.6,-2.9) {[if the customer asks for one]};"+"\n"
    b += r"\draw[dashed,line width=0.6pt] (p.south)--(9.6,-2.58);"+"\n"
    return b

def dvd_en():
    b  = r"\fill[ink] (-1.3,0) circle (0.16);"+"\n"
    b += r"\node[state] (av) at (1.2,0) {Available};"+"\n"
    b += r"\node[state] (bo) at (6.2,0) {Borrowed};"+"\n"
    b += r"\node[state] (re) at (11.6,2.4) {Reminded};"+"\n"
    b += r"\node[state] (ov) at (11.6,-2.4) {Overdue};"+"\n"
    b += rf"\draw[line,{ARR}] (-1.14,0)--(av.west);"+"\n"
    b += rf"\draw[line,{ARR}] (av.east)--(bo.west) node[midway,above,lab]{{borrow}};"+"\n"
    b += rf"\draw[line,{ARR}] (bo.south west) to[bend left=20] node[midway,below,lab]{{return}} (av.south east);"+"\n"
    b += rf"\draw[line,{ARR}] (bo.north east) -- node[above,sloped,lab]{{tick [days >= 10]}} (re.west);"+"\n"
    b += rf"\draw[line,{ARR}] (bo.south east) -- node[below,sloped,lab]{{tick [days > 14]}} (ov.west);"+"\n"
    b += rf"\draw[line,{ARR}] (re.north) to[bend right=35] node[above,lab]{{return}} (av.north);"+"\n"
    b += rf"\draw[line,{ARR}] (ov.south) to[bend left=35] node[below,lab]{{return / fine()}} (av.south);"+"\n"
    return b

def lecteur_fr():
    b  = r"\fill[ink] (-1.2,0) circle (0.16);"+"\n"
    b += r"\node[state] (at) at (1.4,0) {Attente};"+"\n"
    b += r"\node[state] (le) at (6.4,0) {Lecture};"+"\n"
    b += r"\node[state] (pa) at (6.4,-3.4) {Pause};"+"\n"
    b += r"\node[state] (er) at (1.4,-3.4) {Erreur};"+"\n"
    b += r"\node[circle,draw=ink,line width=0.8pt,minimum size=0.55cm] (fi) at (10.8,-3.4) {};\fill[ink] (fi) circle (0.17);"+"\n"
    b += rf"\draw[line,{ARR}] (-1.04,0)--(at.west);"+"\n"
    b += rf"\draw[line,{ARR}] (at.east)--(le.west) node[midway,above,lab]{{play}};"+"\n"
    b += rf"\draw[line,{ARR}] (le.south) to[bend left=18] node[right,lab]{{pause}} (pa.north);"+"\n"
    b += rf"\draw[line,{ARR}] (pa.north west) to[bend left=18] node[left,lab]{{play}} (le.south west);"+"\n"
    b += rf"\draw[line,{ARR}] (le.north) to[bend right=40] node[above,lab]{{stop}} (at.north);"+"\n"
    b += rf"\draw[line,{ARR}] (pa.east)--(fi.west) node[midway,above,lab]{{\'eteindre}};"+"\n"
    b += rf"\draw[line,{ARR}] (er.north)--(at.south) node[midway,right,lab]{{reset}};"+"\n"
    return b.replace("\\'eteindre","éteindre")

def lanes_en():
    xs = [0,4.2,8.4,12.6]
    names = ['Customer','Sales','Warehouse']
    b = ""
    for i,n in enumerate(names):
        b += rf"\draw[line] ({xs[i]},0.9) rectangle ({xs[i+1]},-10.2);"+"\n"
        b += rf"\node[font=\bfseries] at ({(xs[i]+xs[i+1])/2},0.5) {{{n}}};"+"\n"
    b += r"\draw[line] (0,0.1)--(12.6,0.1);"+"\n"
    b += r"\fill[ink] (2.1,-0.55) circle (0.16);"+"\n"
    b += r"\node[act] (a1) at (2.1,-1.9) {Place order};"+"\n"
    b += r"\node[act] (a2) at (6.3,-3.6) {Validate order};"+"\n"
    b += r"\node[act] (a3) at (6.3,-5.5) {Prepare shipment};"+"\n"
    b += r"\node[act] (a4) at (10.5,-7.2) {Ship parcel};"+"\n"
    b += r"\node[act] (a5) at (2.1,-8.8) {Receive parcel};"+"\n"
    b += r"\node[circle,draw=ink,line width=0.8pt,minimum size=0.5cm] (fi) at (2.1,-9.75) {};\fill[ink] (fi) circle (0.15);"+"\n"
    e = lambda p,q,o="": rf"\draw[line,{ARR}] {p}--{q};"+"\n"
    b += e("(2.1,-0.7)","(a1.north)")
    b += rf"\draw[line,{ARR}] (a1.east)-|(a2.north);"+"\n"
    b += e("(a2.south)","(a3.north)")
    b += rf"\draw[line,{ARR}] (a3.east)-|(a4.north);"+"\n"
    b += rf"\draw[line,{ARR}] (a4.south)|-(a5.east);"+"\n"
    b += e("(a5.south)","(fi.north)")
    return b

def paiement_fr():
    b  = r"\fill[ink] (0,0) circle (0.16);"+"\n"
    b += r"\node[act,minimum width=4.2cm] (m) at (0,-1.5) {Choisir le mode de paiement};"+"\n"
    b += r"\node[dec] (d) at (0,-3.1) {};"+"\n"
    b += r"\node[act] (c) at (-3.2,-4.9) {Payer par carte};"+"\n"
    b += r"\node[act] (e) at (3.2,-4.9) {Payer en esp\`eces};"+"\n"
    b += r"\fill[ink] (-1.4,-6.55) rectangle (1.4,-6.43);"+"\n"
    b += r"\node[act] (r) at (0,-7.7) {Imprimer le re\c{c}u};"+"\n"
    b += r"\node[circle,draw=ink,line width=0.8pt,minimum size=0.5cm] (fi) at (0,-9.0) {};\fill[ink] (fi) circle (0.15);"+"\n"
    b += rf"\draw[line,{ARR}] (0,-0.16)--(m.north);"+"\n"
    b += rf"\draw[line,{ARR}] (m.south)--(d.north);"+"\n"
    b += rf"\draw[line,{ARR}] (d.west)-|(c.north) node[pos=0.3,above,lab]{{[carte]}};"+"\n"
    b += rf"\draw[line,{ARR}] (d.east)-|(e.north) node[pos=0.3,above,lab]{{[esp\`eces]}};"+"\n"
    b += rf"\draw[line,{ARR}] (c.south)|-(-1.0,-6.43);"+"\n"
    b += rf"\draw[line,{ARR}] (e.south)|-(1.0,-6.43);"+"\n"
    b += rf"\draw[line,{ARR}] (0,-6.55)--(r.north);"+"\n"
    b += rf"\draw[line,{ARR}] (r.south)--(fi.north);"+"\n"
    return b

def head(i,x,y,label):
    return rf"\node[box,minimum width=2.4cm] ({i}) at ({x},{y}) {{{label}}};"+"\n"
def life(i,ybot):
    return rf"\draw[dashed,line width=0.7pt] ({i}.south)--({i}.south |- 0,{ybot});"+"\n"
def msg(x1,x2,y,label,kind="sync",above=True):
    st = {"sync":SYNC,"async":OPEN,"ret":"dashed,"+OPEN,"create":"dashed,"+OPEN}[kind]
    pos = "above" if above else "below"
    return rf"\draw[line,{st}] ({x1},{y})--({x2},{y}) node[midway,{pos},lab]{{{label}}};"+"\n"

def seq_creation_fr():
    b  = head('c',0,0,':Client')+head('k',3.8,0,':Controleur')+head('o',7.6,0,':Commande')+head('f',11.4,-5.0,':Facture')
    for i,y in (('c',-8.2),('k',-8.2),('o',-8.2),('f',-8.2)): b += life(i,y)
    b += msg(0,3.8,-1.3,"valider()")
    b += msg(3.8,7.6,-2.3,"calculerTotal()")
    b += msg(7.6,3.8,-3.0,"total","ret",False)
    b += msg(3.8,11.4,-3.9,"emettre()")
    b += msg(3.8,10.2,-5.0,"«create»","create")
    b += msg(11.4,3.8,-6.4,"facture","ret",False)
    return b

def atm_seq_en():
    b  = head('c',0,0,':Customer')+head('a',4.2,0,':ATM')+head('k',8.4,0,':Bank')
    for i in 'cak': b += life(i,-8.0)
    b += msg(0,4.2,-1.2,"insertCard()")
    b += msg(0,4.2,-2.1,"enterPIN(pin)")
    b += msg(4.2,8.4,-3.0,"validate(pin)")
    b += msg(8.4,4.2,-3.7,"valid : boolean","ret",False)
    b += r"\draw[line width=0.9pt] (-1.0,-4.3) rectangle (9.6,-7.5);"+"\n"
    b += r"\draw[line width=0.9pt,fill=soft] (-1.0,-4.3)--(0.5,-4.3)--(0.75,-4.62)--(0.75,-4.9)--(-1.0,-4.9)--cycle;\node[font=\footnotesize\bfseries] at (-0.12,-4.6) {opt};"+"\n"
    b += r"\node[lab,anchor=west] at (-0.9,-5.1) {[valid]};"+"\n"
    b += msg(4.2,0,-5.4,"showMenu()")
    b += r"\draw[dashed,line width=0.7pt] (-1.0,-6.0)--(9.6,-6.0);"+"\n"
    b += r"\node[lab,anchor=west] at (-0.9,-6.25) {[else]};"+"\n"
    b += msg(4.2,0,-6.8,"ejectCard()")
    return b

def seq_async_fr():
    b  = head('a',0,0,':Appli')+head('s',4.4,0,':Service')+head('j',8.8,0,':Journal')
    for i in 'asj': b += life(i,-8.0)
    b += msg(0,4.4,-1.3,"valider()")
    b += msg(4.4,0,-2.0,"ok","ret",False)
    b += msg(0,8.8,-3.3,"journaliser(evt)","async")
    b += msg(8.8,0,-4.1,"acquittement","ret",False)
    b += msg(0,4.4,-5.4,"terminer()")
    b += msg(4.4,0,-6.1,"ok","ret",False)
    return b

def comm_en():
    obj = {'cl':(0,0,':Client'),'ca':(5.2,0,':Cart'),'cg':(10.4,2.4,':Catalog'),'st':(10.4,-2.4,':Stock'),'ma':(5.2,-3.8,':Mailer')}
    b = ""
    for k,(x,y,n) in obj.items(): b += rf"\node[box,minimum width=2.1cm] ({k}) at ({x},{y}) {{\underline{{{n}}}}};"+"\n"
    for p,q in (('cl','ca'),('ca','cg'),('ca','st'),('ca','ma'),('st','cg')):
        b += rf"\draw[line] ({p})--({q});"+"\n"
    lab = lambda x,y,t: rf"\node[lab,fill=white] at ({x},{y}) {{{t}}};"+"\n"
    b += lab(2.6,0.45,"1 : addBook(isbn)  $\\rightarrow$") + lab(2.6,-0.45,"2 : checkout()  $\\rightarrow$")
    b += lab(7.7,1.7,"1.1 : find(isbn)  $\\nearrow$")
    b += lab(7.9,-1.5,"1.2 : reserve(isbn)  $\\searrow$")
    b += lab(11.9,0,"1.2.1 : check(isbn)  $\\uparrow$")
    b += lab(5.2,-2.0,"1.10 : notify()  $\\downarrow$")
    return b

def socket_open_right(x0,y):
    # a required interface: stub ends at x0, cup "(" opens to the right
    return rf"\draw[line] ({x0+0.32},{y+0.32}) arc[start angle=90,end angle=270,radius=0.32];"+"\n"
def socket_open_left(x0,y):
    return rf"\draw[line] ({x0-0.32},{y-0.32}) arc[start angle=-90,end angle=90,radius=0.32];"+"\n"

def composants_fr():
    b  = r"\node[box,minimum width=3.2cm,minimum height=1.6cm] (cl) at (0,0) {«component»\\\textbf{Agence}};"+"\n"
    b += r"\node[box,minimum width=3.4cm,minimum height=1.6cm] (pl) at (6.0,0) {«component»\\\textbf{Planificateur}};"+"\n"
    b += r"\node[box,minimum width=3.4cm,minimum height=1.6cm] (re) at (12.4,0) {«component»\\\textbf{Reservation}};"+"\n"
    # Agence requires ActualiserPlan (cup), Planificateur provides it (ball): a valid assembly
    b += r"\draw[line] (cl.east)--(1.75,0);"+"\n"+socket_open_right(1.75,0)
    b += r"\draw[line,fill=white] (2.22,0) circle (0.19);\draw[line] (2.41,0)--(pl.west);"+"\n"
    b += r"\node[lab] at (2.6,0.85) {ActualiserPlan};"+"\n"
    # Planificateur requires FaireReservation (cup) ; Reservation draws a cup as well (the flaw)
    b += r"\draw[line] (pl.east)--(8.25,0);"+"\n"+socket_open_right(8.25,0)
    b += r"\draw[line] (re.west)--(10.35,0);"+"\n"+socket_open_left(10.35,0)
    b += r"\node[lab] at (9.3,0.85) {FaireReservation};"+"\n"
    return b

def cube(i,x,y,w,h,label):
    d = 0.4
    s  = rf"\draw[line,fill=soft] ({x},{y}) rectangle ({x+w},{y+h});"
    s += rf"\draw[line,fill=soft!70!ink!20] ({x},{y+h})--({x+d},{y+h+d})--({x+w+d},{y+h+d})--({x+w},{y+h})--cycle;"
    s += rf"\draw[line,fill=soft!70!ink!20] ({x+w},{y})--({x+w+d},{y+d})--({x+w+d},{y+h+d})--({x+w},{y+h})--cycle;"
    s += rf"\node[anchor=north west,align=left] at ({x+0.12},{y+h-0.1}) {{{label}}};"+"\n"
    return s

def deploy_en():
    b  = cube('n1',0,0,4.6,3.0,r"«device»\\\textbf{Web Server}")
    b += r"\node[box,minimum width=2.6cm,fill=white,anchor=south west] (art) at (7.6,0.7) {«artifact»\\app.war};"+"\n"
    b += rf"\draw[dashed,line width=0.8pt,{OPEN}] (0.2,1.4)--(art.west) node[midway,above,lab]{{«deploy»}};"+"\n"
    b += cube('n2',0,-4.6,4.6,3.0,r"«device»\\\textbf{DB Server}")
    b += r"\node[box,minimum width=2.6cm,fill=white,anchor=south west] at (1.0,-4.0) {«artifact»\\schema.sql};"+"\n"
    return b

def vcycle_fr():
    cx=9.0
    L_ = [("Analyse des besoins\\\\et faisabilit\\'e",8.8,6.4),("Sp\\'ecifications",6.6,4.8),("Conception\\\\architecturale",4.4,3.2),("Conception\\\\d\\'etaill\\'ee",2.2,1.6),("Codage",0,0.0)]
    R_ = [("Recette",8.8,6.4),("Tests\\\\d\\textquotesingle int\\'egration",6.6,4.8),("Tests de\\\\validation",4.4,3.2),("Tests\\\\unitaires",2.2,1.6)]
    b = ""
    for i,(t,off,y) in enumerate(L_): b += rf"\node[box,minimum width=3.1cm,minimum height=1.0cm,fill=white] (l{i}) at ({cx-off},{y}) {{{t}}};"+"\n"
    for i,(t,off,y) in enumerate(R_): b += rf"\node[box,minimum width=3.1cm,minimum height=1.0cm,fill=white] (r{i}) at ({cx+off},{y}) {{{t}}};"+"\n"
    for i in range(4): b += rf"\draw[line] (l{i}.south)--(l{i+1}.north);"+"\n"
    b += r"\draw[line] (l4.east)--(r3.south);"+"\n"
    for i in range(3): b += rf"\draw[line] (r{3-i}.north)--(r{2-i}.south);"+"\n"
    for i in range(4): b += rf"\draw[line,<->] (l{i}.east)--(r{i}.west);"+"\n"
    return b

DIAGRAMS = {'dab_en':dab_en,'dvd_en':dvd_en,'lecteur_fr':lecteur_fr,'lanes_en':lanes_en,'paiement_fr':paiement_fr,
 'seq_creation_fr':seq_creation_fr,'atm_seq_en':atm_seq_en,'seq_async_fr':seq_async_fr,'comm_en':comm_en,
 'composants_fr':composants_fr,'deploy_en':deploy_en,'vcycle_fr':vcycle_fr}
