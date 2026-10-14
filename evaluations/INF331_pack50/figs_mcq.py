# -*- coding: utf-8 -*-
from common import *

DASH = "dashed,line width=0.8pt,-{Straight Barb[length=8pt,width=8pt]}"

def usecase_shop_en():
    b  = r"\draw[line] (2.3,-5.6) rectangle (15.6,4.8);"+"\n"
    b += r"\node[font=\bfseries] at (8.9,4.4) {Online shop};"+"\n"
    b += r"\actor{0.5}{0.2}{Customer}"+"\n"
    b += r"\actor{18.2}{-1.4}{Payment gateway}"+"\n"
    b += r"\node[uc] (pl) at (6.2,0.2) {Place order};"+"\n"
    b += r"\node[uc] (au) at (11.4,2.8) {Authenticate};"+"\n"
    b += r"\node[uc] (co) at (5.4,3.4) {Apply coupon};"+"\n"
    b += r"\node[uc] (pa) at (12.0,-1.2) {Pay};"+"\n"
    b += r"\node[uc] (pc) at (8.8,-4.2) {Pay by card};"+"\n"
    b += r"\node[uc] (pm) at (13.6,-4.2) {Pay by mobile money};"+"\n"
    b += r"\draw[line] (0.8,0.3)--(pl.west);"+"\n"
    b += r"\draw[line] (17.9,-1.2)--(pa.east);"+"\n"
    b += rf"\draw[{DASH}] (pl.north east)--(au.south west) node[midway,above,sloped,lab]{{«include»}};"+"\n"
    b += rf"\draw[{DASH}] (pl.south east)--(pa.west) node[midway,below,sloped,lab]{{«include»}};"+"\n"
    b += rf"\draw[{DASH}] (co.south)--(pl.north) node[midway,right,lab]{{«extend»}};"+"\n"
    b += r"\draw[line,-{Triangle[open,length=10pt,width=10pt]}] (pc.north)--(pa.south west);"+"\n"
    b += r"\draw[line,-{Triangle[open,length=10pt,width=10pt]}] (pm.north)--(pa.south east);"+"\n"
    return b

def objclass_fr():
    # class diagram on the left, object diagram on the right: exactly one inconsistency (e2 is linked to two departments)
    b  = r"\node[font=\bfseries] at (3.6,6.3) {Diagramme de classes};"+"\n"
    b += cls('U',0.4,4.6,'Université',['- nom : String'],[],width=3.0,parts=2)
    b += cls('D',0.4,1.2,'Département',['- code : String'],[],width=3.0,parts=2)
    b += cls('E',6.6,1.2,'Enseignant',['- nom : String'],[],width=3.0,parts=2)
    b += cls('C',6.6,-2.4,'Cours',['- intitulé : String'],[],width=3.0,parts=2)
    b += L(COMP)+" (U.south)--(D.north);\n"
    b += r"\node[lab,anchor=west] at ([xshift=0.1cm,yshift=-0.35cm]U.south) {1};"+"\n"
    b += r"\node[lab,anchor=west] at ([xshift=0.1cm,yshift=0.35cm]D.north) {1..*};"+"\n"
    b += L("")+" (D.east)--(E.west) node[midway,above,lab]{emploie};\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]D.east) {1};"+"\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=0.05cm]E.west) {0..*};"+"\n"
    b += L("")+" (E.south)--(C.north) node[midway,right,lab]{enseigne};\n"
    b += r"\node[lab,anchor=north west] at ([xshift=0.1cm,yshift=-0.05cm]E.south) {1..*};"+"\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]C.north) {1..2};"+"\n"
    # objects
    b += r"\node[font=\bfseries] at (16.2,6.3) {Diagramme d'objets};"+"\n"
    b += r"\node[box,minimum width=2.6cm] (u1) at (16.2,4.8) {\underline{u1 : Université}};"+"\n"
    b += r"\node[box,minimum width=2.4cm] (d1) at (13.0,2.6) {\underline{d1 : Département}};"+"\n"
    b += r"\node[box,minimum width=2.4cm] (d2) at (19.4,2.6) {\underline{d2 : Département}};"+"\n"
    b += r"\node[box,minimum width=2.2cm] (e1) at (12.2,0.0) {\underline{e1 : Enseignant}};"+"\n"
    b += r"\node[box,minimum width=2.2cm] (e2) at (16.2,0.0) {\underline{e2 : Enseignant}};"+"\n"
    b += r"\node[box,minimum width=2.2cm] (e3) at (20.2,0.0) {\underline{e3 : Enseignant}};"+"\n"
    b += r"\node[box,minimum width=2.0cm] (c1) at (14.2,-2.6) {\underline{c1 : Cours}};"+"\n"
    b += r"\node[box,minimum width=2.0cm] (c2) at (18.6,-2.6) {\underline{c2 : Cours}};"+"\n"
    for a,c in (('u1','d1'),('u1','d2'),('d1','e1'),('d1','e2'),('d2','e3'),('e1','c1'),('e2','c1'),('e2','c2'),('e3','c2'),('d2','e2')):
        b += rf"\draw[line] ({a}.south)--({c}.north);"+"\n"
    b = b.replace(r"\draw[line] (e2.south)--(c2.north);", r"\draw[line] (e2.south)--(c2.north west);")
    return b

def seq_cart_en():
    b  = head('c',0,0,':Client')+head('k',4.6,0,':Cart')+head('s',9.2,0,':Stock')+head('p',13.8,0,':Payment')
    for i in 'cksp': b += life(i,-10.6)
    b += msg(0,4.6,-1.2,"checkout()")
    # loop fragment
    b += r"\draw[line width=0.9pt] (-1.2,-1.9) rectangle (11.4,-7.6);"+"\n"
    b += r"\draw[line width=0.9pt,fill=soft] (-1.2,-1.9)--(0.9,-1.9)--(1.2,-2.25)--(1.2,-2.5)--(-1.2,-2.5)--cycle;\node[font=\footnotesize\bfseries] at (-0.1,-2.2) {loop};"+"\n"
    b += r"\node[lab,anchor=west] at (1.4,-2.2) {[for each item of the cart]};"+"\n"
    b += r"\draw[line width=0.9pt] (-0.6,-2.9) rectangle (10.8,-7.2);"+"\n"
    b += r"\draw[line width=0.9pt,fill=soft] (-0.6,-2.9)--(0.8,-2.9)--(1.05,-3.2)--(1.05,-3.45)--(-0.6,-3.45)--cycle;\node[font=\footnotesize\bfseries] at (0.2,-3.2) {alt};"+"\n"
    b += r"\node[lab,anchor=west] at (1.3,-3.2) {[item in stock]};"+"\n"
    b += msg(4.6,9.2,-4.2,"reserve(item)")
    b += r"\draw[dashed,line width=0.7pt] (-0.6,-5.0)--(10.8,-5.0);"+"\n"
    b += r"\node[lab,anchor=west] at (-0.5,-5.25) {[else]};"+"\n"
    b += msg(4.6,0,-6.3,"notifyBackorder(item)")
    b += msg(4.6,13.8,-8.7,"charge(total)")
    b += r"\node[note,anchor=west,text width=5.6cm] at (14.6,-2.0) {Scenario: the cart holds 3 items; the stock has 2 of them, the third is out of stock.};"+"\n"
    return b

def state_hist_fr():
    b  = r"\fill[ink] (-1.2,0) circle (0.16);"+"\n"
    b += r"\node[state] (of) at (1.0,0) {Éteint};"+"\n"
    b += rf"\draw[line,{ARR}] (-1.04,0)--(of.west);"+"\n"
    b += r"\draw[line,rounded corners=10pt] (3.6,-6.3) rectangle (16.6,3.9);"+"\n"
    b += r"\node[font=\bfseries,anchor=north west] at (3.8,3.8) {Allumé};"+"\n"
    b += r"\fill[ink] (4.6,2.2) circle (0.14);"+"\n"
    b += r"\node[state] (ve) at (7.0,2.2) {Veille};"+"\n"
    b += rf"\draw[line,{ARR}] (4.74,2.2)--(ve.west);"+"\n"
    b += r"\draw[line,rounded corners=10pt] (9.8,-5.8) rectangle (16.2,3.2);"+"\n"
    b += r"\node[font=\bfseries,anchor=north west] at (10.0,3.1) {Actif};"+"\n"
    b += r"\fill[ink] (10.8,1.5) circle (0.14);"+"\n"
    b += r"\node[state,minimum width=2.0cm] (le) at (13.6,1.5) {Lecture};"+"\n"
    b += r"\node[state,minimum width=2.0cm] (pa) at (13.6,-1.6) {Pause};"+"\n"
    b += rf"\draw[line,{ARR}] (10.94,1.5)--(le.west);"+"\n"
    b += rf"\draw[line,{ARR}] (le.south) to[bend left=18] node[right,lab]{{pause}} (pa.north);"+"\n"
    b += rf"\draw[line,{ARR}] (pa.north west) to[bend left=18] node[left,lab]{{play}} (le.south west);"+"\n"
    b += rf"\draw[line,{ARR}] (ve.east)--(9.8,2.2) node[midway,above,lab]{{wake}};"+"\n"
    b += r"\node[circle,draw=ink,line width=0.8pt,fill=white,inner sep=1pt,minimum size=0.7cm,font=\footnotesize\bfseries] (h) at (6.8,-2.6) {H*};"+"\n"
    b += r"\node[state] (ma) at (1.0,-3.4) {Maintenance};"+"\n"
    b += rf"\draw[line,{ARR}] (of.east)--(3.6,0) node[midway,above,lab]{{power}};"+"\n"
    b += rf"\draw[line,{ARR}] (3.6,-1.2)--(ma.north east) node[midway,above,sloped,lab]{{alarm}};"+"\n"
    b += rf"\draw[line,{ARR}] (ma.east)--(h.west) node[midway,above,lab]{{resume}};"+"\n"
    b += rf"\draw[line,{ARR}] (3.6,1.0)--(of.north east) node[midway,above,sloped,lab]{{off}};"+"\n"
    return b

def assoc_enroll_en():
    b  = cls('S',0,0,'Student',['- name : String','- id : String'],[],width=3.4)
    b += cls('C',10.4,0,'Course',['- title : String'],[],width=3.4)
    b += L("")+" (S.east)--(C.west) node[midway,above,lab,yshift=0.05cm]{takes};\n"
    b += r"\node[lab,anchor=south west] at ([xshift=0.1cm,yshift=0.05cm]S.east) {1..5};"+"\n"
    b += r"\node[lab,anchor=south east] at ([xshift=-0.1cm,yshift=0.05cm]C.west) {3..30};"+"\n"
    b += cls('E',5.2,-3.9,'Enrollment',['- grade : int'],[],width=3.0,parts=2)
    b += r"\draw[dashed,line width=0.7pt] (E.north)--(5.2,-0.02);"+"\n"
    return b

def act_fork_en():
    b  = r"\fill[ink] (0,6.2) circle (0.17);"+"\n"
    b += r"\node[act] (r) at (0,4.6) {Receive order};"+"\n"
    b += rf"\draw[line,{ARR}] (0,6.03)--(r.north);"+"\n"
    b += r"\fill[ink] (-2.4,3.1) rectangle (2.4,3.25);"+"\n"
    b += rf"\draw[line,{ARR}] (r.south)--(0,3.25);"+"\n"
    b += r"\node[act] (iv) at (-5.2,1.6) {Prepare invoice};"+"\n"
    b += r"\node[dec] (dc) at (4.2,1.6) {};"+"\n"
    b += rf"\draw[line,{ARR}] (-2.4,3.1)--(-5.2,3.1)--(iv.north);"+"\n"
    b += rf"\draw[line,{ARR}] (2.4,3.1)--(4.2,3.1)--(dc.north);"+"\n"
    b += r"\node[act] (co) at (1.6,-0.6) {Book courier};"+"\n"
    b += r"\node[act] (po) at (6.8,-0.6) {Book postal};"+"\n"
    b += rf"\draw[line,{ARR}] (dc.south west)--(co.north) node[midway,left,lab]{{[express]}};"+"\n"
    b += rf"\draw[line,{ARR}] (dc.south east)--(po.north) node[midway,right,lab]{{[standard]}};"+"\n"
    b += r"\fill[ink] (-6.4,-2.8) rectangle (8.6,-2.65);"+"\n"
    b += rf"\draw[line,{ARR}] (iv.south)--(-5.2,-2.65);"+"\n"
    b += rf"\draw[line,{ARR}] (co.south)--(1.6,-2.65);"+"\n"
    b += rf"\draw[line,{ARR}] (po.south)--(6.8,-2.65);"+"\n"
    b += r"\node[act] (sh) at (1.0,-4.4) {Ship order};"+"\n"
    b += rf"\draw[line,{ARR}] (1.0,-2.8)--(sh.north);"+"\n"
    b += r"\node[circle,draw=ink,line width=0.8pt,minimum size=0.55cm] (fi) at (1.0,-6.2) {};\fill[ink] (fi) circle (0.17);"+"\n"
    b += rf"\draw[line,{ARR}] (sh.south)--(fi.north);"+"\n"
    return b

def pkg_cycle_en():
    b  = r"\node[draw=ink,line width=0.8pt,fill=soft,minimum width=4.6cm,minimum height=1.5cm,align=center] (ui) at (0,5.4) {\textbf{app.ui}};"+"\n"
    b += r"\node[draw=ink,line width=0.8pt,fill=soft,minimum width=4.6cm,minimum height=1.5cm,align=center] (bi) at (-3.8,1.8) {\textbf{domain.billing}\\ {\footnotesize Invoice, TaxPolicy}};"+"\n"
    b += r"\node[draw=ink,line width=0.8pt,fill=soft,minimum width=4.6cm,minimum height=1.5cm,align=center] (sh) at (3.8,1.8) {\textbf{domain.shipping}\\ {\footnotesize Parcel, RateTable}};"+"\n"
    b += r"\node[draw=ink,line width=0.8pt,fill=soft,minimum width=4.6cm,minimum height=1.5cm,align=center] (db) at (0,-2.2) {\textbf{infra.persistence}};"+"\n"
    b += rf"\draw[{DASH}] (ui.south west)--(bi.north) node[midway,left,lab]{{«use»}};"+"\n"
    b += rf"\draw[{DASH}] (ui.south east)--(sh.north) node[midway,right,lab]{{«use»}};"+"\n"
    b += rf"\draw[{DASH}] ([yshift=0.25cm]bi.east)--([yshift=0.25cm]sh.west) node[midway,above,lab]{{RateTable.quote()}};"+"\n"
    b += rf"\draw[{DASH}] ([yshift=-0.25cm]sh.west)--([yshift=-0.25cm]bi.east) node[midway,below,lab]{{Invoice.total()}};"+"\n"
    b += rf"\draw[{DASH}] (bi.south)--(db.north west);"+"\n"
    b += rf"\draw[{DASH}] (sh.south)--(db.north east);"+"\n"
    return b

DIAGRAMS = dict(usecase_shop_en=usecase_shop_en, objclass_fr=objclass_fr, seq_cart_en=seq_cart_en, state_hist_fr=state_hist_fr,
                assoc_enroll_en=assoc_enroll_en, act_fork_en=act_fork_en, pkg_cycle_en=pkg_cycle_en)
