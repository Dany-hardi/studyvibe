# -*- coding: utf-8 -*-
from common import *

DASH = "dashed,line width=0.8pt,-{Straight Barb[length=8pt,width=8pt]}"
TRI = "-{Triangle[open,length=10pt,width=10pt]}"

def uc_bib_fr():
    b  = r"\draw[line] (3.2,-8.6) rectangle (16.2,5.4);"+"\n"
    b += r"\node[font=\bfseries] at (9.7,5.0) {Bibliothèque universitaire};"+"\n"
    b += r"\actor{0.6}{2.2}{Membre}"+"\n"
    b += r"\actor{0.6}{-4.2}{Bibliothécaire}"+"\n"
    b += r"\node[uc] (ca) at (7.4,4.0) {Catalogue des livres};"+"\n"
    b += r"\node[uc] (em) at (7.4,1.2) {Emprunter un livre};"+"\n"
    b += r"\node[uc] (ve) at (13.2,3.8) {Vérifier l'adhésion};"+"\n"
    b += r"\node[uc] (au) at (13.2,0.2) {S'authentifier};"+"\n"
    b += r"\node[uc] (re) at (7.4,-2.4) {Rendre un livre};"+"\n"
    b += r"\node[uc] (mj) at (7.4,-6.0) {Mettre à jour la table EXEMPLAIRE};"+"\n"
    b += r"\node[uc,minimum width=3.4cm] (am) at (20.6,-3.6) {Payer l'amende};"+"\n"
    b += r"\draw[line] (0.9,2.4)--(em.west);"+"\n"
    b += r"\draw[line] (0.9,2.6)--(ca.west);"+"\n"
    b += r"\draw[line] (0.9,-4.0)--(mj.west);"+"\n"
    b += r"\draw[line] (0.9,-3.9)--(re.west);"+"\n"
    b += r"\draw[line] (0.6,1.1)--(0.6,-3.2) node[midway,right,lab]{valide};"+"\n"
    b += rf"\draw[{DASH}] (ve.west)--(em.north east) node[midway,above,sloped,lab]{{«include»}};"+"\n"
    b += rf"\draw[{DASH}] (em.south east)--(au.west) node[midway,below,sloped,lab]{{«extend»}};"+"\n"
    b += r"\draw[line] (em.south)--(re.north);"+"\n"
    b += r"\draw[line] (am.west)--(re.east);"+"\n"
    return b

def uc_ticket_en():
    b  = r"\draw[line] (3.0,-5.2) rectangle (14.6,4.4);"+"\n"
    b += r"\node[font=\bfseries] at (8.8,4.0) {Rail ticketing};"+"\n"
    b += r"\actor{0.5}{0.0}{Passenger}"+"\n"
    b += r"\actor{17.4}{-1.0}{Payment provider}"+"\n"
    b += r"\node[uc] (bk) at (6.6,0.2) {Book a ticket};"+"\n"
    b += r"\node[uc] (ch) at (6.6,3.0) {Choose a seat};"+"\n"
    b += r"\node[uc] (pa) at (11.6,-1.0) {Pay};"+"\n"
    b += r"\node[uc] (sn) at (11.6,2.4) {Send the e-ticket};"+"\n"
    b += r"\node[uc] (rf) at (6.6,-3.6) {Apply a rail pass};"+"\n"
    b += r"\draw[line] (0.8,0.1)--(bk.west);"+"\n"
    b += r"\draw[line] (17.1,-1.0)--(pa.east);"+"\n"
    b += rf"\draw[{DASH}] (ch.south)--(bk.north) node[midway,right,lab]{{«extend»}};"+"\n"
    b += rf"\draw[{DASH}] (bk.east)--(pa.north west) node[midway,above,sloped,lab]{{«include»}};"+"\n"
    b += rf"\draw[{DASH}] (bk.north east)--(sn.south west) node[midway,above,sloped,lab]{{«include»}};"+"\n"
    b += rf"\draw[{DASH}] (rf.north)--(bk.south) node[midway,right,lab]{{«extend»}};"+"\n"
    return b

def objviol_fr():
    b  = r"\node[font=\bfseries] at (3.9,7.6) {Diagramme de classes};"+"\n"
    b += cls('P',0.6,5.6,'Projet',['- nom : String'],[],width=3.0,parts=2)
    b += cls('T',0.6,1.6,'Tâche',['- titre : String'],[],width=3.0,parts=2)
    b += cls('D',7.2,1.6,'Développeur',['- nom : String'],[],width=3.0,parts=2)
    b += cls('E',7.2,-2.8,'Équipe',['- code : String'],[],width=3.0,parts=2)
    b += cls('M',7.2,5.6,'Membre',['- id : String'],[],width=3.0,parts=2,abstract=True)
    b += L(COMP)+" (P.south)--(T.north);\n"
    b += r"\node[lab,anchor=west] at ([xshift=0.1cm,yshift=-0.35cm]P.south) {1};"+"\n"
    b += r"\node[lab,anchor=west] at ([xshift=0.1cm,yshift=0.35cm]T.north) {1..*};"+"\n"
    b += L("")+" (T.east)--(D.west) node[midway,above,lab]{assignée à};\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]T.east) {0..3};"+"\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=0.05cm]D.west) {1};"+"\n"
    b += L("")+" (D.south)--(E.north) node[midway,right,lab]{compose};\n"
    b += r"\node[lab,anchor=north west] at ([xshift=0.1cm,yshift=-0.05cm]D.south) {2..5};"+"\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]E.north) {1};"+"\n"
    b += L(GEN)+" (D.north)--(M.south);\n"
    b += r"\node[font=\bfseries] at (18.4,7.6) {Diagramme d'objets};"+"\n"
    def ob(i,x,y,t,w=2.2): return rf"\node[box,minimum width={w}cm] ({i}) at ({x},{y}) {{\underline{{{t}}}}};"+"\n"
    b += ob('p1',14.2,5.8,'p1 : Projet',2.2)+ob('p2',22.4,5.8,'p2 : Projet',2.2)
    b += ob('t1',13.0,3.2,'t1 : Tâche',2.0)+ob('t2',16.6,3.2,'t2 : Tâche',2.0)+ob('t3',20.2,3.2,'t3 : Tâche',2.0)
    b += ob('d1',13.0,0.2,'d1 : Développeur',2.7)+ob('d2',17.8,0.2,'d2 : Développeur',2.7)+ob('m1',22.6,0.2,'m1 : Membre',2.2)
    b += ob('e1',13.0,-3.0,'e1 : Équipe',2.2)+ob('e2',18.0,-3.0,'e2 : Équipe',2.2)
    b += ob('t4',22.6,3.2,'t4 : Tâche',2.0)
    for a,c in (('p1','t1'),('p1','t2'),('p1','t3'),('p2','t3'),('t1','d1'),('t2','d1'),('t2','d2'),('t4','d1'),('t3','d1'),('d1','e1'),('d2','e2')):
        b += rf"\draw[line] ({a}.south)--({c}.north);"+"\n"
    b += r"\draw[line] (t1.south east)--(e2.north west);"+"\n"
    return b

def classcrit_en():
    b  = cls('Cu',0,5.4,'Customer',['- name : String','- customerName : String','+ email : String'],['+ placeOrder() : Order'],width=4.0)
    b += cls('O',9.6,5.4,'Order',['- date : Date','- totalPrice : double','- customer : Customer'],['+ addLine(l : OrderLine)'],width=4.2)
    b += cls('Ca',9.6,-0.4,'Cart',['- createdAt : Date'],[],width=4.2)
    b += cls('Ol',2.2,0.4,'OrderLine',['- quantity : int','- unitPrice : double'],[],width=4.0)
    b += cls('I',9.6,-4.8,'Invoice',['- number : String','- issuedOn : Date'],[],width=4.2)
    b += L("")+" (Cu.east)--(O.west) node[midway,above,lab]{places};\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]Cu.east) {1..*};"+"\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=0.05cm]O.west) {0..*};"+"\n"
    b += L(COMP)+" (O.south west)--(Ol.north east);\n"
    b += L(COMP)+" (Ca.west)--(Ol.east);\n"
    b += r"\node[lab,anchor=north west] at ([xshift=0.15cm,yshift=-0.04cm]Ol.north east) {1..*};"+"\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]Ol.east) {1..*};"+"\n"
    b += L(GEN)+" (I.north east)--++(1.5,0)--++(0,8.6)-|(O.east);\n"
    b = b.replace("(I.north east)--++(1.5,0)--++(0,8.6)-|(O.east);","(I.north east)--++(1.5,0)|-(O.east);")
    return b

def seq_defect_fr():
    b  = head('e',0,0,':Étudiant')+head('p',4.6,0,':Portail')+head('y',9.2,0,':Paiement')+head('k',13.8,0,':Banque')
    for i in 'epyk': b += life(i,-10.4)
    b += msg(0,4.6,-1.3,"payer(montant)")
    b += msg(4.6,9.2,-2.4,"débiter(montant)")
    b += msg(9.2,13.8,-3.5,"autoriser(compte, montant)")
    b += r"\draw[line width=0.9pt] (-1.2,-4.3) rectangle (15.4,-6.5);"+"\n"
    b += r"\draw[line width=0.9pt,fill=soft] (-1.2,-4.3)--(0.5,-4.3)--(0.75,-4.62)--(0.75,-4.9)--(-1.2,-4.9)--cycle;\node[font=\footnotesize\bfseries] at (-0.25,-4.6) {alt};"+"\n"
    b += r"\node[lab,anchor=west] at (1.0,-4.6) {[accepté]};"+"\n"
    b += msg(4.6,0,-5.7,"confirmer()")
    b += r"\draw[line width=1.1pt] (13.5,-7.2)--(14.1,-7.8)(13.5,-7.8)--(14.1,-7.2);"+"\n"
    b += r"\node[lab,anchor=west] at (14.4,-7.5) {destruction};"+"\n"
    b += msg(4.6,13.8,-8.9,"archiver()")
    b += r"\node[note,anchor=north west,text width=6.2cm] at (15.2,-0.4) {Interfaces des classes :\\ Portail.payer(montant : double)\\ Paiement.débiter(montant : double, compte : String) : boolean\\ Banque.autoriser(compte : String, montant : double) : boolean\\ Portail.confirmer()};"+"\n"
    return b

def state_defect_en():
    b  = r"\fill[ink] (-1.2,0) circle (0.16);"+"\n"
    b += r"\node[state,minimum width=2.4cm] (id) at (1.4,0) {Idle\\ {\footnotesize entry / showReady}};"+"\n"
    b += rf"\draw[line,{ARR}] (-1.04,0)--(id.west);"+"\n"
    b += r"\node[state,minimum width=3.0cm] (pr) at (8.4,2.6) {Printing\\ {\footnotesize entry / startMotor}\\ {\footnotesize exit / stopMotor}};"+"\n"
    b += r"\node[state,minimum width=2.4cm] (qu) at (8.4,-2.6) {Queued};"+"\n"
    b += r"\node[state,minimum width=2.4cm] (er) at (14.8,0) {Error};"+"\n"
    b += r"\node[state,minimum width=2.4cm] (ar) at (1.4,-5.6) {Archived};"+"\n"
    b += rf"\draw[line,{ARR}] (id.north east) to[bend left=14] node[above,sloped,lab]{{submit [pages $>$ 0]}} (pr.west);"+"\n"
    b += rf"\draw[line,{ARR}] (id.south east) to[bend right=14] node[below,sloped,lab]{{submit [pages $\geq$ 1]}} (qu.west);"+"\n"
    b += rf"\draw[line,{ARR}] (pr.south west) to[bend left=14] node[left,lab,xshift=-0.05cm]{{cancel / logCancel}} (id.north);"+"\n"
    b += rf"\draw[line,{ARR}] (pr.east) to[bend left=10] node[above,sloped,lab]{{jam}} (er.north);"+"\n"
    b += rf"\draw[line,{ARR}] (qu.north) -- node[right,lab]{{printerFree}} (pr.south);"+"\n"
    b += rf"\draw[line,{ARR}] (qu.east) to[bend right=10] node[below,sloped,lab]{{jam}} (er.south);"+"\n"
    return b

def act_lanes_fr():
    xs=[0,5.8,11.6,17.4]; names=['Étudiant','Scolarité','Comptabilité']
    b=""
    for i,n in enumerate(names):
        b += rf"\draw[line] ({xs[i]},1.0) rectangle ({xs[i+1]},-14.6);"+"\n"
        b += rf"\node[font=\bfseries] at ({(xs[i]+xs[i+1])/2},0.55) {{{n}}};"+"\n"
    b += r"\fill[ink] (2.9,-0.4) circle (0.15);"+"\n"
    b += r"\node[act,minimum width=3.0cm] (a1) at (2.9,-1.8) {Remplir la demande};"+"\n"
    b += rf"\draw[line,{ARR}] (2.9,-0.55)--(a1.north);"+"\n"
    b += r"\node[act,minimum width=3.0cm] (a2) at (8.7,-1.8) {Vérifier le dossier};"+"\n"
    b += rf"\draw[line,{ARR}] (a1.east)--(a2.west);"+"\n"
    b += r"\node[dec] (d1) at (8.7,-4.0) {};"+"\n"
    b += rf"\draw[line,{ARR}] (a2.south)--(d1.north);"+"\n"
    b += r"\node[act,minimum width=3.0cm] (a3) at (2.9,-4.0) {Compléter le dossier};"+"\n"
    b += rf"\draw[line,{ARR}] (d1.west)--(a3.east) node[midway,above,lab]{{[incomplet]}};"+"\n"
    b += rf"\draw[line,{ARR}] (a3.north)--(a1.south);"+"\n"
    b += r"\fill[ink] (7.2,-5.9) rectangle (15.4,-5.75);"+"\n"
    b += rf"\draw[line,{ARR}] (d1.south)--(8.7,-5.75) node[midway,right,lab]{{[complet]}};"+"\n"
    b += r"\node[act,minimum width=3.0cm] (a4) at (8.7,-7.6) {Passer le dossier à l'état « Validé »};"+"\n"
    b += r"\node[act,minimum width=3.0cm] (a5) at (14.5,-7.6) {Calculer le montant dû};"+"\n"
    b += rf"\draw[line,{ARR}] (8.7,-5.9)--(a4.north);"+"\n"
    b += rf"\draw[line,{ARR}] (14.5,-5.9)--(a5.north);"+"\n"
    b += r"\node[box,minimum width=2.6cm,minimum height=0.8cm,font=\small] (ob) at (11.6,-9.7) {\underline{dossier : Dossier}};"+"\n"
    b += rf"\draw[line,{ARR}] (a4.south)--(8.7,-9.7)--(ob.west);"+"\n"
    b += rf"\draw[line,{ARR}] (a5.south)--(14.5,-9.7)--(ob.east);"+"\n"
    b += r"\fill[ink] (6.0,-11.2) rectangle (16.0,-11.05);"+"\n"
    b += rf"\draw[line,{ARR}] (ob.south)--(11.6,-11.05);"+"\n"
    b += r"\node[act,minimum width=3.0cm] (a6) at (14.5,-12.6) {Ordonner le virement};"+"\n"
    b += rf"\draw[line,{ARR}] (11.6,-11.2)--(11.6,-12.6)--(a6.west);"+"\n"
    b += r"\node[circle,draw=ink,line width=0.8pt,minimum size=0.55cm] (fi) at (14.5,-13.9) {};\fill[ink] (fi) circle (0.17);"+"\n"
    b += rf"\draw[line,{ARR}] (a6.south)--(fi.north);"+"\n"
    return b

def deploy_crit_en():
    def cube(i,x,y,w,h,label):
        return rf"\node[box,minimum width={w}cm,minimum height={h}cm,align=center,fill=soft] ({i}) at ({x},{y}) {{{label}}};"+"\n"
    b  = cube('cl',0,0,3.4,2.2,r"«device»\\ \textbf{Client browser}")
    b += cube('w',7.0,0,3.8,2.2,r"«device» \textbf{web01}\\ «executionEnvironment»\\ Nginx")
    b += cube('a',14.0,0,3.8,2.2,r"«device» \textbf{app01}\\ «executionEnvironment»\\ Tomcat 10\\ «artifact» shop.war")
    b += cube('d',14.0,-4.8,3.8,2.2,r"«device» \textbf{db01}\\ «executionEnvironment»\\ PostgreSQL 15")
    b += cube('bk',7.0,-4.8,3.8,2.0,r"«device» \textbf{backup01}\\ nightly dump (tape)")
    b += rf"\draw[line] (cl.east)--(w.west) node[midway,above,lab]{{«HTTP»}};"+"\n"
    b += rf"\draw[line] (w.east)--(a.west) node[midway,above,lab]{{«AJP»}};"+"\n"
    b += rf"\draw[line] (a.south)--(d.north) node[midway,right,lab]{{«JDBC»}};"+"\n"
    b += rf"\draw[line] (d.west)--(bk.east) node[midway,above,lab]{{«SCP»}};"+"\n"
    b += r"\node[note,anchor=north west,text width=7cm] at (-1.6,-7.0) {All three servers are in the same network segment, reachable from the Internet on ports 80, 8009 and 5432. Admin console of Tomcat: port 8080, password authentication only.};"+"\n"
    return b

def pattern_mystery_fr():
    b  = cls('N',5.6,5.4,'Notifier',[],['+ send(msg : String)'],width=4.2,stereo='interface',parts=2).replace("rectangle split part align={center,left}","")
    b += cls('Em',0.0,0.8,'EmailNotifier',[],['+ send(msg : String)'],width=4.2,parts=2)
    b += cls('D',9.6,0.8,'NotifierDecorator',['\\# wrapped : Notifier'],['+ send(msg : String)'],width=4.6,abstract=True)
    b += cls('Sm',5.4,-4.2,'SmsDecorator',[],['+ send(msg : String)'],width=4.2,parts=2)
    b += cls('Sl',13.8,-4.2,'SlackDecorator',[],['+ send(msg : String)'],width=4.2,parts=2)
    b += cls('Cl',13.8,5.4,'Client',[],[],width=3.0,parts=2)
    b += L(REAL)+" (Em.north)--++(0,1.2)-|([xshift=-0.9cm]N.south);\n"
    b += L(REAL)+" (D.north)--++(0,1.2)-|([xshift=0.9cm]N.south);\n"
    b += L(GEN)+" (Sm.north)--++(0,1.0)-|([xshift=-0.9cm]D.south);\n"
    b += L(GEN)+" (Sl.north)--++(0,1.0)-|([xshift=0.9cm]D.south);\n"
    b += L(NAV)+" (Cl.west)--(N.east);\n"
    return b

def grasp_en():
    b  = cls('O',0,3.2,'Order',['- id : int','- date : Date'],[],width=3.6)
    b += cls('Ol',7.0,3.2,'OrderLine',['- quantity : int'],[],width=3.6)
    b += cls('P',14.0,3.2,'Product',['- code : String','- unitPrice : double','- stock : int'],[],width=3.8)
    b += cls('Ca',14.0,-2.6,'Catalog',[],[],width=3.8,parts=2)
    b += cls('Pr',0,-2.6,'Promotion',['- rate : double','- label : String'],[],width=3.6)
    b += cls('Cu',7.0,-2.6,'CustomerAccount',['- loyaltyPoints : int'],[],width=3.8)
    b += L(COMP)+" (O.east)--(Ol.west) node[midway,above,lab]{1};\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=0.04cm]Ol.west) {1..*};"+"\n"
    b += L(NAV)+" (Ol.east)--(P.west);\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=0.04cm]P.west) {1};"+"\n"
    b += L(AGG)+" (Ca.north)--(P.south);\n"
    b += L("")+" (Pr.north)--(O.south) node[midway,right,lab]{applies to};\n"
    b += L("")+" (Cu.north)--(O.south east) node[midway,right,lab]{places};\n"
    return b

def diamond2_en():
    b  = cls('P',6.0,5.2,'Person',['- name : String','- id : String'],['+ getId() : String'],width=3.8)
    b += cls('S',0.8,0.4,'Student',['- program : String'],['+ enrol(c : Course)','+ getId() : String'],width=4.2)
    b += cls('E',11.4,0.4,'Employee',['- salary : double'],['+ pay()','+ getId() : String'],width=4.2)
    b += cls('T',6.0,-4.6,'ThesisStudent',['- topic : String'],['+ defend()'],width=4.2)
    b += L(GEN)+" (S.north)--++(0,1.0)-|([xshift=-0.9cm]P.south);\n"
    b += L(GEN)+" (E.north)--++(0,1.0)-|([xshift=0.9cm]P.south);\n"
    b += L(GEN)+" (T.north west)--++(0,0.8)-|(S.south);\n"
    b += L(GEN)+" (T.north east)--++(0,0.8)-|(E.south);\n"
    return b

def comp_req_fr():
    def comp(i,x,y,w,h,label):
        return rf"\node[box,minimum width={w}cm,minimum height={h}cm,align=center] ({i}) at ({x},{y}) {{«component»\\ \textbf{{{label}}}}};"+"\n"
    def assembly(provider_edge, socket_edge, cx, name_above, name_below=None):
        # ball from the provider (right), socket from the requirer (left) wrapping the ball
        t  = rf"\draw[line] ({provider_edge},0)--({cx+0.3},0);"+"\n"
        t += rf"\draw[line] ({cx},0) circle (0.3);"+"\n"
        t += rf"\draw[line] ({socket_edge},0)--({cx-0.55},0);"+"\n"
        t += rf"\draw[line] ({cx},0.55) arc (90:270:0.55);"+"\n"
        t += rf"\node[lab] at ({cx},1.1) {{{name_above}}};"+"\n"
        return t
    b  = comp('m',0,0,3.6,2.0,'AppMobile')
    b += comp('b',9.4,0,3.6,2.0,'Boutique')
    b += comp('g',19.6,0,4.4,2.0,'PasserellePaiement')
    b += assembly(7.6, 1.8, 5.4, "ICommande")
    b += r"\draw[line] (11.2,0)--(13.4,0);"+"\n"+r"\draw[line] (17.4,0)--(15.3,0);"+"\n"
    b += r"\draw[line] (15.0,0) circle (0.3);"+"\n"
    b += r"\draw[line] (15.0,0.55) arc (90:270:0.55);"+"\n"
    b += r"\draw[line] (13.4,0)--(14.45,0);"+"\n"
    b += r"\node[lab] at (13.2,1.0) {IPaiement (requise)};"+"\n"
    b += r"\node[lab] at (14.6,-1.0) {IPaiementV2 (fournie)};"+"\n"
    b += r"\node[note,anchor=north west,text width=9.0cm] at (-1.4,-2.4) {IPaiement : payer(montant : double) : boolean\\ IPaiementV2 : payer(montant : double, devise : String) : Reçu\\ \hspace*{5.2em}annuler(reçu : Reçu)};"+"\n"
    return b

def comm_fr():
    obj = {'cl':(0,0,':Client'),'pa':(6.0,0,':Panier'),'ca':(12.0,2.6,':Catalogue'),'st':(12.0,-2.6,':Stock'),'fa':(6.0,-4.4,':Facturation')}
    b = ""
    for k,(x,y,n) in obj.items(): b += rf"\node[box,minimum width=2.3cm] ({k}) at ({x},{y}) {{\underline{{{n}}}}};"+"\n"
    for p,q in (('cl','pa'),('pa','ca'),('pa','st'),('pa','fa'),('st','ca')):
        b += rf"\draw[line] ({p})--({q});"+"\n"
    lab = lambda x,y,t: rf"\node[lab,fill=white] at ({x},{y}) {{{t}}};"+"\n"
    b += lab(3.0,0.5,"1 : ajouterLivre(isbn) $\\rightarrow$") + lab(3.0,-0.5,"2 : valider() $\\rightarrow$")
    b += lab(8.8,1.9,"1.1 : chercher(isbn) $\\nearrow$") + lab(9.1,-1.4,"1.2 : réserver(isbn) $\\searrow$")
    b += lab(13.9,0,"1.2.1 : vérifier(isbn) $\\uparrow$") + lab(6.0,-2.2,"2.2 : émettre(panier) $\\downarrow$")
    b += lab(6.0,1.55,"2.1 : calculerTotal()")
    b += r"\draw[line] ([xshift=-0.5cm]pa.north) .. controls +(-0.2,1.0) and +(0.2,1.0) .. ([xshift=0.5cm]pa.north);"+"\n"
    return b

DIAGRAMS = dict(uc_bib_fr=uc_bib_fr, uc_ticket_en=uc_ticket_en, objviol_fr=objviol_fr, classcrit_en=classcrit_en, seq_defect_fr=seq_defect_fr,
                state_defect_en=state_defect_en, act_lanes_fr=act_lanes_fr, deploy_crit_en=deploy_crit_en, pattern_mystery_fr=pattern_mystery_fr,
                grasp_en=grasp_en, diamond2_en=diamond2_en, comp_req_fr=comp_req_fr, comm_fr=comm_fr)
