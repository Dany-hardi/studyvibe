# -*- coding: utf-8 -*-
"""Figures for the questions where the student writes code."""
from common import *

IFACE = dict(stereo='interface')

def strategie_livraison_fr():
    b  = cls('Pa',0,3.4,'Panier',['- total : int','- poidsKg : double'],['+ definirStrategie(s : StrategieLivraison)','+ fraisLivraison() : int'],width=5.4)
    b += cls('S',10.6,3.4,'StrategieLivraison',[],['+ cout(poidsKg : double, total : int) : int'],width=6.0,stereo='interface',parts=2).replace("rectangle split part align={center,left}","")
    b += cls('St',3.4,-2.2,'LivraisonStandard',[],[],width=4.2,parts=2)
    b += cls('Ex',10.6,-2.2,'LivraisonExpress',[],[],width=4.2,parts=2)
    b += cls('Re',17.8,-2.2,'Retrait',[],[],width=3.4,parts=2)
    b += L(NAV)+" (Pa.east)--(S.west);\n"
    b += r"\node[lab,anchor=south] at ([xshift=-1.0cm]S.west) {strategie};"+"\n"
    b += L(REAL)+" (St.north)--++(0,1.2)-|([xshift=-1.4cm]S.south);\n"
    b += L(REAL)+" (Ex.north)--(S.south);\n"
    b += L(REAL)+" (Re.north)--++(0,1.2)-|([xshift=1.4cm]S.south);\n"
    return b

def observer_class_en():
    b  = cls('Su',0,4.4,'Sensor',['- value : double','- threshold : double'],['+ attach(o : Observer)','+ detach(o : Observer)','+ setValue(v : double)'],width=5.0)
    b += cls('Ob',10.4,4.4,'Observer',[],['+ update(v : double)'],width=4.2,stereo='interface',parts=2).replace("rectangle split part align={center,left}","")
    b += cls('Da',6.8,-1.4,'Dashboard',['- lastValue : double'],[],width=4.2,parts=2)
    b += cls('Al',14.4,-1.4,'AlarmLogger',['- alarms : int'],[],width=4.2,parts=2)
    b += L(NAV)+" (Su.east)--(Ob.west) node[midway,above,lab]{observers};\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=-0.35cm]Ob.west) {0..*};"+"\n"
    b += L(REAL)+" (Da.north)--++(0,1.2)-|([xshift=-0.9cm]Ob.south);\n"
    b += L(REAL)+" (Al.north)--++(0,1.2)-|([xshift=0.9cm]Ob.south);\n"
    return b

def state_doc_fr():
    b  = r"\fill[ink] (-1.2,0) circle (0.16);"+"\n"
    b += r"\node[state,minimum width=2.6cm] (br) at (1.6,0) {Brouillon};"+"\n"
    b += r"\node[state,minimum width=2.6cm] (rl) at (8.2,0) {En relecture};"+"\n"
    b += r"\node[state,minimum width=2.6cm] (pu) at (14.8,2.2) {Publié};"+"\n"
    b += r"\node[state,minimum width=2.6cm] (rj) at (14.8,-2.2) {Rejeté};"+"\n"
    b += r"\node[circle,draw=ink,line width=0.8pt,minimum size=0.55cm] (fi) at (19.4,-2.2) {};\fill[ink] (fi) circle (0.17);"+"\n"
    b += rf"\draw[line,{ARR}] (-1.04,0)--(br.west);"+"\n"
    b += rf"\draw[line,{ARR}] (br.east)--(rl.west) node[midway,above,lab]{{soumettre [titre non vide]}};"+"\n"
    b += rf"\draw[line,{ARR}] (rl.north east)--(pu.west) node[midway,above,sloped,lab]{{approuver [relecteurs $\geq$ 2]}};"+"\n"
    b += rf"\draw[line,{ARR}] (rl.south east)--(rj.west) node[midway,below,sloped,lab]{{rejeter}};"+"\n"
    b += rf"\draw[line,{ARR}] (rj.north) to[bend right=0] node[left,lab]{{}} (br.north |- rj.north) ;"+"\n"
    b += rf"\draw[line,{ARR}] (rj.south west) to[bend left=22] node[below,lab]{{corriger}} (br.south);"+"\n"
    b += rf"\draw[line,{ARR}] (pu.north) to[bend right=35] node[above,lab]{{retirer}} (br.north);"+"\n"
    b += rf"\draw[line,{ARR}] (rj.east)--(fi.west) node[midway,above,lab]{{abandonner}};"+"\n"
    b += r"\node[lab,anchor=west] at (4.2,-3.6) {Un événement non prévu dans un état est ignoré.};"+"\n"
    b = b.replace(rf"\draw[line,{ARR}] (rj.north) to[bend right=0] node[left,lab]{{}} (br.north |- rj.north) ;"+"\n","")
    return b

def spy_class_en():
    b  = cls('R',0,3.0,'InvoiceReminder',['- clock : Clock','- mailer : Mailer'],['+ remindOverdue(invoices : List<Invoice>) : int'],width=6.4)
    b += cls('C',11.8,5.6,'Clock',[],['+ today() : LocalDate'],width=4.4,stereo='interface',parts=2).replace("rectangle split part align={center,left}","")
    b += cls('M',11.8,0.8,'Mailer',[],['+ send(to : String, subject : String)'],width=5.6,stereo='interface',parts=2).replace("rectangle split part align={center,left}","")
    b += cls('I',0,-3.2,'Invoice',['- number : String','- dueDate : LocalDate','- paid : boolean','- customerEmail : String'],[],width=5.0)
    b += L(NAV)+" (R.east)--(C.west);\n"
    b += L(NAV)+" (R.east)--(M.west);\n"
    b += L(DEP)+" (R.south)--(I.north);\n"
    b += r"\node[note,anchor=north west,text width=6.4cm] at (8.6,-2.6) {An invoice is overdue when it is not paid and its due date is strictly before today. remindOverdue sends one mail per overdue invoice (subject: ``Invoice <number> is overdue'') and returns how many were sent. If \texttt{send} throws, the exception is caught, the invoice is skipped and counted as not sent.};"+"\n"
    return b

def composite_fr():
    b  = cls('E',5.6,4.6,'Élément',['\\# nom : String'],['+ taille() : long','+ rechercher(ext : String) : List<Fichier>'],width=6.2,abstract=True)
    b += cls('F',0.4,-1.2,'Fichier',['- octets : long','- extension : String'],[],width=4.6)
    b += cls('D',11.8,-1.2,'Dossier',[],['+ ajouter(e : Élément)','+ retirer(e : Élément)'],width=5.4,parts=2)
    b += L(GEN)+" (F.north)--++(0,1.0)-|([xshift=-1.4cm]E.south);\n"
    b += L(GEN)+" (D.north)--++(0,1.0)-|([xshift=1.4cm]E.south);\n"
    b += L(AGG)+" (D.east)--++(1.6,0)--++(0,4.8)--(E.east) ;\n"
    b = b.replace("--++(1.6,0)--++(0,4.8)--(E.east) ;","--++(1.6,0)|-(E.east);")
    b += r"\node[lab,anchor=west] at ([xshift=0.2cm,yshift=1.0cm]D.east) {enfants};"+"\n"
    b += r"\node[lab,anchor=south west] at ([xshift=1.7cm,yshift=0.1cm]D.east) {0..*};"+"\n"
    return b

def invariants_fr():
    b  = cls('Ch',0,3.4,'Chambre',['- numero : String'],['+ reserver(p : Periode) : Reservation','+ estLibre(p : Periode) : boolean'],width=5.6)
    b += cls('R',10.4,3.4,'Réservation',['- client : String'],['+ periode() : Periode'],width=4.6)
    b += cls('Pe',10.4,-2.6,'Periode',['- debut : LocalDate','- fin : LocalDate'],['+ chevauche(autre : Periode) : boolean'],width=5.4,stereo='value object')
    b += L(COMP)+" (Ch.east)--(R.west) node[midway,above,lab]{réservations};\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=-0.34cm]R.west) {0..*};"+"\n"
    b += L(NAV)+" (R.south)--(Pe.north);\n"
    b += r"\node[lab,anchor=north west] at ([xshift=0.1cm,yshift=-0.05cm]R.south) {1};"+"\n"
    b += r"\node[note,anchor=north west,text width=6.6cm] at (-2.4,-1.6) {Invariants : debut $<$ fin ; une Periode ne change jamais après sa création ; deux réservations d'une même chambre ne se chevauchent jamais ; la liste des réservations n'est jamais modifiable de l'extérieur.};"+"\n"
    return b

def orm_inherit_en():
    b  = cls('V',6.0,5.0,'Vehicle',['- plate : String','- brand : String','- year : int'],[],width=4.6,abstract=True)
    b += cls('Ca',-0.6,-0.6,'Car',['- doors : int'],[],width=3.6,parts=2)
    b += cls('Tr',6.0,-0.6,'Truck',['- payloadKg : double','- axles : int'],[],width=4.2,parts=2)
    b += cls('Mo',12.8,-0.6,'Motorbike',['- engineCc : int'],[],width=4.0,parts=2)
    b += cls('Ow',-2.2,5.0,'Owner',['- fullName : String','- email : String'],[],width=4.2,parts=2)
    b += L(GEN)+" (Ca.north)--++(0,1.0)-|([xshift=-1.2cm]V.south);\n"
    b += L(GEN)+" (Tr.north)--(V.south);\n"
    b += L(GEN)+" (Mo.north)--++(0,1.0)-|([xshift=1.2cm]V.south);\n"
    b += L("")+" (Ow.east)--(V.west) node[midway,above,lab]{owns};\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]Ow.east) {1};"+"\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=0.05cm]V.west) {0..*};"+"\n"
    return b

DIAGRAMS = dict(strategie_livraison_fr=strategie_livraison_fr, observer_class_en=observer_class_en, state_doc_fr=state_doc_fr,
                spy_class_en=spy_class_en, composite_fr=composite_fr, invariants_fr=invariants_fr, orm_inherit_en=orm_inherit_en)
