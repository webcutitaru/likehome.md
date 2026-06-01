<?php

declare(strict_types=1);

/** @return array<string, mixed> */
return [
    'label' => 'Legal',
    'updated_note' => 'Descrie cum prelucrăm datele personale și cum folosim cookie-urile pe acest site.',
    'sections' => [
        [
            'title' => '1. Cine suntem',
            'body' => '<p>Operatorul site-ului <strong class="text-ink">Like HOME</strong> (locație: {city}). Contact pentru întrebări privind datele personale: <a href="mailto:{email}" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink">{email}</a>.</p>',
        ],
        [
            'title' => '2. Ce date colectăm',
            'body' => '<ul><li><strong class="text-ink">Date furnizate de tine:</strong> nume, email, telefon, mesaje, detalii de rezervare (date, număr de oaspeți etc.), transmise prin formulare sau email.</li><li><strong class="text-ink">Date tehnice:</strong> adresă IP, tip de browser, pagini vizitate, marcă temporală — în măsura în care sunt generate automat de server sau de instrumente de analiză (dacă ai acceptat categoria respectivă).</li><li><strong class="text-ink">Cookie-uri:</strong> vezi secțiunea dedicată mai jos.</li></ul>',
        ],
        [
            'title' => '3. Scopurile prelucrării',
            'body' => '<ul><li>Răspuns la solicitări și gestionarea rezervărilor.</li><li>Funcționarea securizată a site-ului și a zonei de administrare (sesiuni, protecție CSRF).</li><li>Îmbunătățirea experienței și a conținutului (dacă ai acceptat analiza).</li><li>Măsurare și campanii publicitare relevante (dacă ai acceptat publicitatea / marketingul).</li><li>Îndeplinirea obligațiilor legale, unde este cazul.</li></ul>',
        ],
        [
            'title' => '4. Temei legal (GDPR)',
            'body' => '<p>Prelucrarea poate fi necesară pentru executarea unui contract sau pași precontractuali (rezervare), pe baza consimțământului tău (cookie-uri neesențiale, newsletter dacă există), sau pe baza interesului legitim (securitate, statistici agregate anonime unde este permis).</p>',
        ],
        [
            'title' => '5. Cookie-uri și tehnologii similare',
            'body' => '<p class="mb-4">Folosim cookie-uri și stocare locală (ex. <code class="text-sm bg-black/[0.05] px-1 rounded">localStorage</code>) pentru a memora alegerile tale privind consimțământul. Poți modifica oricând preferințele din site (link „Preferințe cookie-uri” în subsol).</p><h3 class="text-ink font-semibold text-base mb-2">Categorii</h3><ul class="space-y-3"><li><strong class="text-ink">Strict necesare</strong> — sesiune PHP pentru funcții esențiale (ex. administrare), securitate; fără ele unele funcții nu pot funcționa.</li><li><strong class="text-ink">Analitică</strong> — înțelegem cum este folosit site-ul (ex. Google Analytics), doar dacă accepți.</li><li><strong class="text-ink">Publicitate și marketing</strong> — măsurare conversii, remarketing, reclame personalizate prin parteneri (ex. Google Ads, Meta), doar dacă accepți.</li></ul><p class="mt-4 text-sm">Conținut încorporat (ex. hărți) poate seta cookie-uri ale terților conform politicilor lor.</p>',
        ],
        [
            'title' => '6. Destinatari și transferuri',
            'body' => '<p>Putem folosi furnizori de găzduire, email și servicii de analiză / publicitate. Unii pot avea sedii în afara SEE; în astfel de cazuri ne asigurăm că există garanții adecvate (clauze contractuale tip, decizii de adecvare etc.), conform legislației aplicabile.</p>',
        ],
        [
            'title' => '7. Durata păstrării',
            'body' => '<p>Păstrăm datele atât timp cât este necesar pentru scopurile de mai sus sau conform obligațiilor legale (ex. contabilitate). Cookie-urile au durate variate; cele de sesiune expiră la închiderea browserului, altele pot dura luni dacă le permite furnizorul.</p>',
        ],
        [
            'title' => '8. Drepturile tale',
            'body' => '<p>În condițiile legii poți solicita acces, rectificare, ștergere, restricționare, portabilitate, opoziție și retragerea consimțământului pentru prelucrările bazate pe consimțământ. Poți depune plângere la autoritatea de supraveghere din țara ta.</p>',
        ],
        [
            'title' => '9. Copii',
            'body' => '<p>Site-ul nu se adresează în mod intenționat minorilor sub 16 ani fără acordul titularilor de răspundere parentală.</p>',
        ],
    ],
];
