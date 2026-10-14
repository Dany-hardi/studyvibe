from uml import *

def L(x): return f"\\draw[line,{x}]"

def oeuvre_fr():
    b = cls('a',0,3.2,r'\OE uvre',['- titre : String','- auteur : String'],[],width=3.2)
    b += cls('l',-4,0.2,'Livre',[],[],width=2.6)+cls('o',0,0.2,r'Op\'era',[],[],width=2.6)+cls('f',4,0.2,'Film',[],[],width=2.6)
    b = b.replace("Op\\'era","Opéra")
    b += L(GEN)+" (l.north)--++(0,0.6)-|(a.south);\n"
    b += L(GEN)+" (o.north)--(a.south);\n"
    b += L("{Triangle[open,length=10pt,width=10pt]}-")+" (f.north)--++(0,0.6)-|(a.south);\n"
    return b

def hotel_en():
    b = cls('h',0,0,'Hotel',['- name : String','- stars : int'],[],width=3.0)
    b += cls('r',7,0,'Room',['- number : int','- beds : int'],[],width=3.0)
    b += cls('g',3.5,-3.4,'Guest',['- name : String'],[],width=3.0)
    b += L(COMP)+" (h.east)--(r.west);\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.15cm,yshift=0.04cm]h.east) {2..*};"+"\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.15cm,yshift=0.04cm]r.west) {1..*};"+"\n"
    b += L("")+" (r.south)|-(g.east);\n"
    b += L("")+" (h.south)|-(g.west);\n"
    b += r"\node[lab,below=0.05cm of h.south,anchor=north west] {0..*};"+"\n"
    b += r"\node[lab,below=0.05cm of r.south,anchor=north east] {0..*};"+"\n"
    return b

def objets_fr():
    b  = r"\node[font=\bfseries] at (5.6,5.6) {Diagramme de classes};"+"\n"
    b += cls('E',1.6,3.6,'Entreprise',['- nom : String'],[],width=3.0,parts=2)
    b += cls('P',9.6,3.6,'Personne',['- nom : String'],[],width=3.0,parts=2)
    b += L("")+" (E.east)--(P.west) node[midway,above,lab]{emploie};\n"
    b += r"\node[lab,above=0.05cm of E.east,anchor=south west,xshift=0.1cm] {0..2};"+"\n"
    b += r"\node[lab,above=0.05cm of P.west,anchor=south east,xshift=-0.1cm] {2..*};"+"\n"
    b += r"\node[font=\bfseries] at (5.6,1.6) {Diagramme d'objets};"+"\n"
    ents = {'e1':0.2,'e2':3.2,'e3':6.0,'e4':10.4}
    pers = {'p1':-1.6,'p2':1.0,'p3':3.6,'p6':6.2,'p4':8.8,'p5':11.4}
    for k,x in ents.items():
        b += rf"\node[box,minimum width=1.9cm] ({k}) at ({x},0.4) {{\underline{{{k} : Entreprise}}}};"+"\n"
    for k,x in pers.items():
        b += rf"\node[box,minimum width=1.9cm] ({k}) at ({x},-2.4) {{\underline{{{k} : Personne}}}};"+"\n"
    links = [('e1','p1'),('e1','p2'),('e1','p6'),('e2','p2'),('e2','p3'),('e2','p6'),('e3','p3'),('e4','p4'),('e4','p5'),('e4','p6')]
    for a,c in links:
        b += rf"\draw[line] ({a}.south)--({c}.north);"+"\n"
    return b

def pay_fr():
    b = cls('i',0,3.0,'Payable',[],['+ payer(m : double) : void'],width=4.4,stereo='interface',parts=2).replace("rectangle split part align={center,left}","")
    b += cls('f',-3.6,-0.2,'Facture',['- montant : double'],['+ payer(m : double) : void'],width=3.6)
    b += cls('a',3.6,-0.2,'Abonnement',['- tarif : double'],['+ payer(m : double) : void'],width=3.6)
    b += L(GEN)+" (f.north)--++(0,0.6)-|([xshift=-0.9cm]i.south);\n"
    b += L(REAL)+" (a.north)--++(0,0.6)-|([xshift=0.9cm]i.south);\n"
    return b

def amphibie_en():
    b = cls('v',0,4.6,'Vehicle',['- speed : int'],['+ move() : void'],width=3.2)
    b += cls('l',-3.3,2.0,'LandVehicle',['- wheels : int'],[],width=3.2)
    b += cls('w',3.3,2.0,'WaterVehicle',['- draught : double'],[],width=3.2)
    b += cls('a',0,-0.6,'Amphibious',[],['+ switchMode() : void'],width=3.2)
    b += L(GEN)+" (l.north)--++(0,0.5)-|(v.south);\n"
    b += L(GEN)+" (w.north)--++(0,0.5)-|(v.south);\n"
    b += L(GEN)+" (a.north)--++(0,0.45)-|(l.south);\n"
    b += L(GEN)+" (a.north)--++(0,0.45)-|(w.south);\n"
    return b

def compte_fr():
    b = cls('c',0,0,'Compte',
        ['- solde : double','\\# numero : int',r'\underline{- nbComptes : int}','/ interets : double'],
        ['+ deposer(m : double) : void','+ getSolde() : double',r'\underline{+ total() : int}'],width=5.4)
    return b

def vehicules_en():
    b = cls('v',0,5.2,'Vehicle',['- type : int'],['+ move() : int'],width=3.4)
    b += cls('t',-5.2,2.0,'Train',[],['+ move() : int'],width=3.0)
    b += cls('c',0,2.0,'Car',[],['+ move() : int'],width=3.0)
    b += cls('b',5.2,2.0,'Boat',[],['+ move() : int'],width=3.0)
    b += L(GEN)+" (t.north)--++(0,0.55)-|(v.south);\n"
    b += L(GEN)+" (c.north)--(v.south);\n"
    b += L(GEN)+" (b.north)--++(0,0.55)-|(v.south);\n"
    b += r"\node[note,anchor=west] (nv) at (2.4,6.2) {Vehicle.move()\\\{ return 1; \}};"+"\n"
    b += r"\node[note] (nt) at (-5.2,-0.9) {Train.move()\\\{ return super.move() * 3; \}};"+"\n"
    b += r"\node[note] (nc) at (0,-0.9) {Car.move()\\\{ return super.move() + 10; \}};"+"\n"
    b += r"\node[note] (nb) at (5.2,-0.9) {Boat.move()\\\{ return 5; \}};"+"\n"
    for n,c in (('nt','t'),('nc','c'),('nb','b')):
        b += rf"\draw[dashed,line width=0.6pt] ({n}.north)--({c}.south);"+"\n"
    b += r"\draw[dashed,line width=0.6pt] (nv.west)--(v.east);"+"\n"
    return b

def noteassoc_fr():
    b = cls('e',0,0,'Etudiant',['- matricule : String'],[],width=3.0,parts=2)
    b += cls('c',5.2,0,'Cours',['- code : String'],[],width=3.0,parts=2)
    b += cls('p',10.4,0,'Enseignant',['- nom : String'],[],width=3.0,parts=2)
    b += L("")+" (e.east)--(c.west) coordinate[midway] (m1);\n"
    b += L("")+" (c.east)--(p.west) coordinate[midway] (m2);\n"
    b += cls('n',5.2,-3.2,'Note',['- valeur : double'],[],width=3.0,parts=2)
    b += r"\draw[dashed,line width=0.7pt] (n.north west)--(m1);"+"\n"
    b += r"\draw[dashed,line width=0.7pt] (n.north east)--(m2);"+"\n"
    return b

def qualif_en():
    b = cls('b',0,0,'Bank',['- name : String'],[],width=3.0,parts=2)
    b += cls('p',8,0,'Person',['- name : String'],[],width=3.0,parts=2)
    b += r"\node[draw=ink,line width=0.8pt,fill=white,minimum width=1.9cm,minimum height=0.75cm,anchor=west] (q) at (b.east) {accountNo};"+"\n"
    b += L("")+" (q.east)--(p.west);\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.15cm,yshift=0.04cm]p.west) {0..2};"+"\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.15cm,yshift=0.04cm]q.east) {1..*};"+"\n"
    return b

def strategie_fr():
    b = cls('c',0,0,'Confrontation',[],['+ confronter(s1 : Strategie,','\\hspace{1.5em}s2 : Strategie) : Resultat'],width=5.8)
    b += cls('s',8.6,0,'Strategie',[],['+ jouer() : Coup'],width=3.2,parts=2).replace("rectangle split part align={center,left}","")
    b += L(COMP)+" (c.east)--(s.west);\n"
    b += r"\node[lab,above=0.06cm of c.east,anchor=south west,xshift=0.1cm] {1};"+"\n"
    b += r"\node[lab,above=0.06cm of s.west,anchor=south east,xshift=-0.1cm] {2};"+"\n"
    return b

def dip_en():
    b = cls('o',0,0,'OrderService',[],['+ placeOrder(o : Order) : void'],width=5.0,parts=2).replace("rectangle split part align={center,left}","")
    b += cls('m',8.2,0,'MySqlOrderRepository',[],['+ save(o : Order) : void','+ find(id : int) : Order'],width=5.4,parts=2).replace("rectangle split part align={center,left}","")
    b += L(NAV)+" (o.east)--(m.west);\n"
    b += r"\node[note,anchor=north west] at (-2.4,-1.5) {placeOrder(o) \{\\ \hspace*{1em}repo = \textbf{new MySqlOrderRepository()};\\ \hspace*{1em}repo.save(o);\\ \}};"+"\n"
    return b

def univ_en():
    b = cls('u',0,0,'University',['- name : String'],[],width=3.0,parts=2)
    b += cls('d',6.4,0,'Department',['- title : String'],[],width=3.0,parts=2)
    b += cls('c',12.8,0,'Course',['- code : String'],[],width=3.0,parts=2)
    b += L(AGG)+" (u.east)--(d.west);\n"
    b += L(COMP)+" (d.east)--(c.west);\n"
    b += r"\node[lab,above=0.06cm of u.east,anchor=south west,xshift=0.12cm] {1};"+"\n"
    b += r"\node[lab,above=0.06cm of d.west,anchor=south east,xshift=-0.12cm] {1..*};"+"\n"
    b += r"\node[lab,above=0.06cm of d.east,anchor=south west,xshift=0.12cm] {1};"+"\n"
    b += r"\node[lab,above=0.06cm of c.west,anchor=south east,xshift=-0.12cm] {1..*};"+"\n"
    return b

DIAGRAMS = {
 'oeuvre_fr': oeuvre_fr, 'hotel_en': hotel_en, 'objets_fr': objets_fr, 'pay_fr': pay_fr, 'amphibie_en': amphibie_en,
 'compte_fr': compte_fr, 'vehicules_en': vehicules_en, 'noteassoc_fr': noteassoc_fr, 'qualif_en': qualif_en,
 'strategie_fr': strategie_fr, 'dip_en': dip_en, 'univ_en': univ_en,
}
