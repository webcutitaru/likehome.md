<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pageTitle = 'Confidențialitate și cookie-uri — Like HOME';
$pageDescription = 'Politica de confidențialitate Like HOME: ce date prelucrăm, cum folosim cookie-urile și cum ne poți contacta pentru drepturile tale.';
$canonicalUrl = lh_absolute_url('privacy.php');
$robotsMeta = 'index, follow, max-snippet:-1';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3">Legal</p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight">Politica de confidențialitate</h1>
    <p class="mt-4 text-sm text-blue-grey">
      Ultima actualizare: <?= htmlspecialchars(date('d.m.Y'), ENT_QUOTES, 'UTF-8') ?>.
      Descrie cum prelucrăm datele personale și cum folosim cookie-urile pe acest site.
    </p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0">
  <div class="space-y-10 text-blue-grey leading-relaxed [&_h2]:text-ink [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-0 [&_h2]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2">

    <section>
      <h2>1. Cine suntem</h2>
      <p>
        Operatorul site-ului <strong class="text-ink">Like HOME</strong> (locație: <?= htmlspecialchars(lh_site_contact_city(), ENT_QUOTES, 'UTF-8') ?>).
        Contact pentru întrebări privind datele personale:
        <a href="mailto:<?= htmlspecialchars(lh_site_contact_email(), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink"><?= htmlspecialchars(lh_site_contact_email(), ENT_QUOTES, 'UTF-8') ?></a>.
      </p>
    </section>

    <section>
      <h2>2. Ce date colectăm</h2>
      <ul>
        <li><strong class="text-ink">Date furnizate de tine:</strong> nume, email, telefon, mesaje, detalii de rezervare (date, număr de oaspeți etc.), transmise prin formulare sau email.</li>
        <li><strong class="text-ink">Date tehnice:</strong> adresă IP, tip de browser, pagini vizitate, marcă temporală — în măsura în care sunt generate automat de server sau de instrumente de analiză (dacă ai acceptat categoria respectivă).</li>
        <li><strong class="text-ink">Cookie-uri:</strong> vezi secțiunea dedicată mai jos.</li>
      </ul>
    </section>

    <section>
      <h2>3. Scopurile prelucrării</h2>
      <ul>
        <li>Răspuns la solicitări și gestionarea rezervărilor.</li>
        <li>Funcționarea securizată a site-ului și a zonei de administrare (sesiuni, protecție CSRF).</li>
        <li>Îmbunătățirea experienței și a conținutului (dacă ai acceptat analiza).</li>
        <li>Măsurare și campanii publicitare relevante (dacă ai acceptat publicitatea / marketingul).</li>
        <li>Îndeplinirea obligațiilor legale, unde este cazul.</li>
      </ul>
    </section>

    <section>
      <h2>4. Temei legal (GDPR)</h2>
      <p>
        Prelucrarea poate fi necesară pentru executarea unui contract sau pași precontractuali (rezervare),
        pe baza consimțământului tău (cookie-uri neesențiale, newsletter dacă există),
        sau pe baza interesului legitim (securitate, statistici agregate anonime unde este permis).
      </p>
    </section>

    <section class="rounded-2xl border border-black/[0.08] bg-white/90 p-6 md:p-8 shadow-sm">
      <h2>5. Cookie-uri și tehnologii similare</h2>
      <p class="mb-4">
        Folosim cookie-uri și stocare locală (ex. <code class="text-sm bg-black/[0.05] px-1 rounded">localStorage</code>) pentru a memora alegerile tale privind consimțământul.
        Poți modifica oricând preferințele din site (link „Preferințe cookie-uri” în subsol).
      </p>
      <h3 class="text-ink font-semibold text-base mb-2">Categorii</h3>
      <ul class="space-y-3">
        <li><strong class="text-ink">Strict necesare</strong> — sesiune PHP pentru funcții esențiale (ex. administrare), securitate; fără ele unele funcții nu pot funcționa.</li>
        <li><strong class="text-ink">Analitică</strong> — înțelegem cum este folosit site-ul (ex. Google Analytics), doar dacă accepți.</li>
        <li><strong class="text-ink">Publicitate și marketing</strong> — măsurare conversii, remarketing, reclame personalizate prin parteneri (ex. Google Ads, Meta), doar dacă accepți.</li>
      </ul>
      <p class="mt-4 text-sm">
        Conținut încorporat (ex. hărți) poate seta cookie-uri ale terților conform politicilor lor.
      </p>
    </section>

    <section>
      <h2>6. Destinatari și transferuri</h2>
      <p>
        Putem folosi furnizori de găzduire, email și servicii de analiză / publicitate. Unii pot avea sedii în afara SEE; în astfel de cazuri ne asigurăm că există garanții adecvate (clauze contractuale tip, decizii de adecvare etc.), conform legislației aplicabile.
      </p>
    </section>

    <section>
      <h2>7. Durata păstrării</h2>
      <p>
        Păstrăm datele atât timp cât este necesar pentru scopurile de mai sus sau conform obligațiilor legale (ex. contabilitate). Cookie-urile au durate variate; cele de sesiune expiră la închiderea browserului, altele pot dura luni dacă le permite furnizorul.
      </p>
    </section>

    <section>
      <h2>8. Drepturile tale</h2>
      <p>
        În condițiile legii poți solicita acces, rectificare, ștergere, restricționare, portabilitate, opoziție și retragerea consimțământului pentru prelucrările bazate pe consimțământ.
        Poți depune plângere la autoritatea de supraveghere din țara ta.
      </p>
    </section>

    <section>
      <h2>9. Copii</h2>
      <p>
        Site-ul nu se adresează în mod intenționat minorilor sub 16 ani fără acordul titularilor de răspundere parentală.
      </p>
    </section>

    <p class="text-sm border-t border-black/[0.08] pt-8">
      <a href="<?= htmlspecialchars(lh_public_url('terms.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4">Termeni și condiții</a>
      ·
      <button type="button" id="lh-privacy-open-cookies" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 bg-transparent border-0 cursor-pointer p-0 text-sm font-inherit">
        Deschide preferințe cookie-uri
      </button>
    </p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
