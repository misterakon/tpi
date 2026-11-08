"""
Prépare les modèles Excel du tribunal pour l'application.

Les classeurs du TPI (dossier « dossier TPI ») sont des TABLEAUX de saisie suivis d'onglets « RECAP » qui se calculent tout seuls.
L'application remplit ces mêmes classeurs avec ses affaires : pour cela on garde le classeur d'origine tel quel (formules, listes déroulantes, couleurs,
onglets protégés) mais on retire les lignes de saisie d'exemple, ce qui le ramène de plusieurs dizaines de Mo à quelques dizaines de Ko.
Il reste, dans chaque tableau, l'en-tête et UNE ligne type vide (styles et formules des colonnes calculées) que l'application recopie pour chaque affaire.

Utilisation :  python tools/preparer_modeles_excel.py "C:/chemin/dossier TPI"
Résultat    :  resources/modeles_excel/*.xlsx
"""
import html
import re
import sys
import zipfile
from pathlib import Path

# code du modèle -> nom du classeur d'origine
MODELES = {
    'civil_presidentielle': 'AUDIENCE CIVILE PRESIDENTIELLE Bingerville.xlsx',
    'refere_voies_execution': "Aud. Référé  Voies d'exécution  Bingerville.xlsx",
    'refere_expulsion': 'Aud. Référé expulsion- VE Bingerville.xlsx',
    'refere_ordinaire': 'Aud. Référé ordinaire Bingerville.xlsx',
    'matrimoniale': 'Audience affaire matrimoniale Bingerville.xlsx',
    'etat_des_personnes': 'Audience état des personnes Bingerville.xlsx',
    'civile_diverse': 'Audience civile diverse Bingerville.xlsx',
    'heredite': 'Audience Hérédité Bingerville.xlsx',
    'tutelle': 'Audience tutelle Bingerville.xlsx',
    'mise_en_etat': 'Audience mise en état Bingerville.xlsx',
    'penal_flagrants_delits': 'Audience FD Bingerville.xlsx',
    'penal_delits_divers': 'Audience délits divers Bingerville.xlsx',
    'penal_delits_economiques': 'Audience délits éco Bingerville.xlsx',
    'penal_police_circulation': 'Audience police circulation Bingerville.xlsx',
    'penal_simple_police': 'Audience Simple Police Bingerville.xlsx',
    'penal_tpe': 'Audience TPE Bingerville.xlsx',
    'personnel_magistrat': 'RH Magistrat Bingerville.xlsx',
    'instruction_ji1': 'INFO JI 1er Cabinet Bingerville.xlsx',
    'instruction_ji2': 'INFO JI 2ème Cabinet Bingerville.xlsx',
    'instruction_je': 'INFO JE Bingerville.xlsx',
    'assistance_judiciaire': 'Assistance judiciaire Bingerville.xlsx',
    'activites_jap': 'Activités du JAP Bingerville.xlsm',
}


def feuilles_et_tables(z):
    """[(chemin_feuille, chemin_table)] pour chaque tableau du classeur."""
    sortie = []
    for nom in z.namelist():
        m = re.match(r'xl/worksheets/_rels/(sheet\d+)\.xml\.rels', nom)
        if not m:
            continue
        rels = z.read(nom).decode('utf8')
        for t in re.findall(r'Target="\.\./tables/(table\d+\.xml)"', rels):
            sortie.append((f'xl/worksheets/{m.group(1)}.xml', f'xl/tables/{t}'))
    return sortie


def vider_ligne_type(row_xml):
    """Garde les styles et les formules de la ligne 5, retire les valeurs d'exemple (et les valeurs en cache des formules)."""
    def cellule(m):
        attrs, corps = m.group(1), m.group(2)
        if corps is not None and '<f' in corps:
            f = re.search(r'<f[^>]*>.*?</f>|<f[^>]*/>', corps, flags=re.S).group(0)
            attrs = re.sub(r'\s+t="[^"]*"', '', attrs)
            return f'<c{attrs}>{f}</c>'
        attrs = re.sub(r'\s+t="[^"]*"', '', attrs)
        return f'<c{attrs}/>'
    return re.sub(r'<c((?:\s+[\w:]+="[^"]*")*)\s*(?:/>|>(.*?)</c>)', cellule, row_xml, flags=re.S)


def reduire_feuille(xml, ref_table, ligne_type=5):
    """Garde les lignes 1 à 4 (titres, en-tête) et une ligne 5 type vide ; supprime le reste de la zone du tableau."""
    debut_ligne5 = xml.find('<row r="%d"' % ligne_type)
    fin_donnees = xml.find('</sheetData>')
    if debut_ligne5 < 0:
        return xml
    fin_ligne5 = xml.find('</row>', debut_ligne5) + len('</row>')
    ligne5 = vider_ligne_type(xml[debut_ligne5:fin_ligne5])
    # un filtre actif du classeur d'origine masquait la ligne type : on la rend visible
    ligne5 = re.sub(r'\s(?:hidden|collapsed|outlineLevel)="[^"]*"', '', ligne5)
    return xml[:debut_ligne5] + ligne5 + xml[fin_donnees:]


def reduire_table(xml, derniere_colonne, entete=4):
    xml = re.sub(r'ref="A%d:[A-Z]+\d+"' % entete, 'ref="A%d:%s%d"' % (entete, derniere_colonne, entete + 1), xml)
    xml = re.sub(r'<sortState[^>]*>.*?</sortState>', '', xml, flags=re.S)
    # retire les filtres actifs (colonnes filtrées) du tableau
    xml = re.sub(r'<autoFilter ref="([^"]*)">.*?</autoFilter>', r'<autoFilter ref="\1"/>', xml, flags=re.S)
    return xml


def feuilles_recap(z):
    """Chemins des feuilles dont le nom commence par RECAP."""
    wb = z.read('xl/workbook.xml').decode('utf8')
    rels = z.read('xl/_rels/workbook.xml.rels').decode('utf8')
    cibles = {}
    for rel in re.findall(r'<Relationship [^>]*/>', rels):
        rid = re.search(r'Id="([^"]+)"', rel).group(1)
        cibles[rid] = re.search(r'Target="([^"]+)"', rel).group(1)
    sortie = []
    for nom, rid in re.findall(r'<sheet [^>]*?name="([^"]+)"[^>]*?r:id="([^"]+)"', wb):
        if html.unescape(nom).upper().startswith('RECAP'):
            sortie.append('xl/' + cibles[rid].lstrip('/').replace('xl/', ''))
    return sortie


def normaliser_recaps(z, modifs):
    """Rend les tableaux RECAP lisibles : police Calibri 11, bordures fines, colonnes plus larges, lignes plus hautes.
    Les couleurs, les textes et les formules ne changent pas."""
    chemins = feuilles_recap(z)
    if not chemins:
        return
    st = z.read('xl/styles.xml').decode('utf8')
    fonts = re.findall(r'<font>.*?</font>|<font/>', re.search(r'<fonts[^>]*>(.*?)</fonts>', st, re.S).group(1), re.S)
    nb_bordures = len(re.findall(r'<border[ >/]', re.search(r'<borders[^>]*>(.*?)</borders>', st, re.S).group(1)))
    xfs = re.findall(r'<xf [^>]*?(?:/>|>.*?</xf>)', re.search(r'<cellXfs[^>]*>(.*?)</cellXfs>', st, re.S).group(1), re.S)
    feuilles = {c: z.read(c).decode('utf8') for c in chemins}
    utilises = sorted({int(i) for x in feuilles.values() for i in re.findall(r' s="(\d+)"', x)})

    gris = '<color rgb="FFBFC9C3"/>'
    bordure = ('<border><left style="thin">%s</left><right style="thin">%s</right><top style="thin">%s</top>'
               '<bottom style="thin">%s</bottom><diagonal/></border>') % (gris, gris, gris, gris)
    nouvelles_polices, index_police = [], {}

    def police(fid):
        f = fonts[fid]
        gras = '<b/>' in f
        taille = 16 if re.search(r'<sz val="(1[6-9]|2\d)"', f) else 11
        couleur = re.search(r'<color [^>]*/>', f)
        cle = (gras, taille, couleur.group(0) if couleur else '')
        if cle not in index_police:
            index_police[cle] = len(fonts) + len(nouvelles_polices)
            nouvelles_polices.append('<font>' + ('<b/>' if gras else '') + '<sz val="%d"/>' % taille + cle[2]
                                     + '<name val="Calibri"/><family val="2"/><scheme val="minor"/></font>')
        return index_police[cle]

    nouveaux_xfs, correspondance = [], {}
    for i in utilises:
        x = xfs[i]
        fid = int(re.search(r'fontId="(\d+)"', x).group(1))
        x = re.sub(r'fontId="\d+"', 'fontId="%d"' % police(fid), x)
        x = re.sub(r'borderId="\d+"', 'borderId="%d"' % nb_bordures, x)
        x = re.sub(r'apply(Font|Border)="0"', r'apply\1="1"', x)
        for a in ('applyFont', 'applyBorder'):
            if a + '=' not in x:
                x = x.replace('<xf ', '<xf %s="1" ' % a, 1)
        if '<alignment' in x and 'vertical=' not in x:
            x = x.replace('<alignment', '<alignment vertical="center"', 1)
        correspondance[i] = len(xfs) + len(nouveaux_xfs)
        nouveaux_xfs.append(x)

    def hauteur(m):
        ligne = m.group(0)
        ligne = re.sub(r'\s(?:ht|customHeight)="[^"]*"', '', ligne)
        return ligne.replace('<row ', '<row ht="21" customHeight="1" ', 1)

    def colonnes(m):
        debut = int(m.group(1))
        largeur = 44 if debut == 1 else (15 if debut == 2 else 11)
        return '<col min="%s" max="%s" width="%d" customWidth="1"/>' % (m.group(1), m.group(2), largeur)

    for chemin, xml in feuilles.items():
        xml = re.sub(r' s="(\d+)"', lambda m: ' s="%d"' % correspondance[int(m.group(1))], xml)
        xml = re.sub(r'<row\b[^>]*>(?=<c )', hauteur, xml)       # seulement les lignes qui ont des cellules (les espaceurs gardent leur hauteur)
        xml = re.sub(r'<col min="(\d+)" max="(\d+)"[^>]*/>', colonnes, xml)
        # valeurs en cache périmées (des zéros d'un classeur vide) : retirées, Excel recalcule à l'ouverture
        xml = re.sub(r'(<f(?:\s[^>]*)?/>|<f(?:\s[^>]*)?>[^<]*</f>)<v>[^<]*</v>', lambda m: m.group(1), xml)
        modifs[chemin] = xml

    st = re.sub(r'(<fonts count=")\d+(")', lambda m: m.group(1) + str(len(fonts) + len(nouvelles_polices)) + m.group(2), st, count=1)
    st = st.replace('</fonts>', ''.join(nouvelles_polices) + '</fonts>', 1)
    st = re.sub(r'(<borders count=")\d+(")', lambda m: m.group(1) + str(nb_bordures + 1) + m.group(2), st, count=1)
    st = st.replace('</borders>', bordure + '</borders>', 1)
    st = re.sub(r'(<cellXfs count=")\d+(")', lambda m: m.group(1) + str(len(xfs) + len(nouveaux_xfs)) + m.group(2), st, count=1)
    st = st.replace('</cellXfs>', ''.join(nouveaux_xfs) + '</cellXfs>', 1)
    modifs['xl/styles.xml'] = st


# Cellules de saisie du classeur des activités du JAP : (ligne, colonnes). Le stock du début du mois ne se saisit qu'en janvier : ensuite c'est une formule.
LIGNES_JAP = {
    'tous_les_mois': [5, 6, 7, 12, 14, 15, 16, 22, 24, 25, 26, 32, 34, 35, 36, 42, 43, 45, 46, 47, 53, 54, 56, 57, 58],
    'janvier_seulement': [11, 21, 31, 41, 52],
}


def vider_cellules_jap(z, modifs):
    wb = z.read('xl/workbook.xml').decode('utf8')
    rels = z.read('xl/_rels/workbook.xml.rels').decode('utf8')
    cibles = {re.search(r'Id="([^"]+)"', r).group(1): re.search(r'Target="([^"]+)"', r).group(1) for r in re.findall(r'<Relationship [^>]*/>', rels)}
    for nom, rid in re.findall(r'<sheet [^>]*?name="([^"]+)"[^>]*?r:id="([^"]+)"', wb):
        if not html.unescape(nom).upper().startswith('ACTIVITES DU JAP'):
            continue
        chemin = 'xl/' + cibles[rid].lstrip('/').replace('xl/', '')
        xml = modifs.get(chemin) or z.read(chemin).decode('utf8')
        cellules = [c + str(r) for r in LIGNES_JAP['tous_les_mois'] for c in 'BCDEFGHIJKLM'] + [c + str(r) for r in LIGNES_JAP['janvier_seulement'] for c in 'B']
        for ref in cellules:
            xml = re.sub(r'<c r="%s"((?:\s+[\w:]+="[^"]*")*)>(?!<f)(?:(?!</c>).)*</c>' % ref, lambda m: '<c r="%s"%s/>' % (ref, re.sub(r'\s+t="[^"]*"', '', m.group(1))), xml, count=1, flags=re.S)
        modifs[chemin] = xml


def preparer(source, cible):
    z = zipfile.ZipFile(source)
    modifs = {}
    for feuille, table in feuilles_et_tables(z):
        t = z.read(table).decode('utf8')
        ref = re.search(r' ref="([^"]+)"', t).group(1)
        entete, derniere = re.match(r'A(\d+):([A-Z]+)\d+', ref).groups()
        entete = int(entete)
        modifs[table] = reduire_table(t, derniere, entete)
        x = z.read(feuille).decode('utf8')
        x = reduire_feuille(x, ref, entete + 1)
        x = re.sub(r'<dimension ref="[^"]*"/>', f'<dimension ref="A1:{derniere}{entete + 1}"/>', x)
        modifs[feuille] = x
    normaliser_recaps(z, modifs)
    if 'ACTIVITES DU JAP' in z.read('xl/workbook.xml').decode('utf8').upper():
        vider_cellules_jap(z, modifs)
    wb = z.read('xl/workbook.xml').decode('utf8')
    wb = re.sub(r'<calcPr[^>]*/>', '<calcPr calcId="191029" fullCalcOnLoad="1"/>', wb)
    modifs['xl/workbook.xml'] = wb
    sans = {'xl/calcChain.xml'}
    ct = z.read('[Content_Types].xml').decode('utf8')
    ct = re.sub(r'<Override[^>]*calcChain[^>]*/>', '', ct)
    modifs['[Content_Types].xml'] = ct
    rels = z.read('xl/_rels/workbook.xml.rels').decode('utf8')
    rels = re.sub(r'<Relationship [^>]*calcChain[^>]*/>', '', rels)
    modifs['xl/_rels/workbook.xml.rels'] = rels
    with zipfile.ZipFile(cible, 'w', zipfile.ZIP_DEFLATED) as out:
        for i in z.infolist():
            if i.filename in sans:
                continue
            out.writestr(i.filename, modifs[i.filename] if i.filename in modifs else z.read(i.filename))


if __name__ == '__main__':
    dossier = Path(sys.argv[1])
    sortie = Path(__file__).resolve().parent.parent / 'resources' / 'modeles_excel'
    sortie.mkdir(parents=True, exist_ok=True)
    for code, nom in MODELES.items():
        cible = sortie / (code + Path(nom).suffix)
        preparer(dossier / nom, cible)
        print(f'{code:28} {cible.stat().st_size // 1024:6d} Ko')
