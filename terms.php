<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pageTitle = 'Termeni și condiții — Like HOME';
$pageDescription = 'Termenii și condițiile de utilizare Like HOME: utilizarea site-ului, rezervările și responsabilitățile părților.';
$canonicalUrl = lh_absolute_url('terms.php');
$robotsMeta = 'index, follow, max-snippet:-1';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3">Legal</p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight">Termeni și condiții de utilizare</h1>
    <p class="mt-4 text-sm text-blue-grey">
      Ultima actualizare: <?= htmlspecialchars(date('d.m.Y'), ENT_QUOTES, 'UTF-8') ?>.
      Text cu caracter informativ; pentru situații concrete consultă un consilier juridic.
    </p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0">
  <div class="prose prose-neutral max-w-none space-y-10 text-blue-grey leading-relaxed [&_h2]:text-ink [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-0 [&_h2]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2">

    <section>
      <h2>1. Operator și acceptare</h2>
      <p>
        Site-ul <strong class="text-ink">Like HOME</strong> este operat în scopul prezentării proprietăților disponibile pentru închiriere pe termen scurt și facilitării solicitărilor de rezervare.
        Prin accesarea și utilizarea site-ului confirmi că ai citit și înțeles acești termeni. Dacă nu ești de acord, te rugăm să nu utilizezi site-ul.
      </p>
    </section>

    <section>
      <h2>2. Serviciul oferit</h2>
      <p>
        Conținutul (descrieri, imagini, prețuri orientative, disponibilitate) are caracter informativ. Oferta finală, condițiile contractuale și confirmarea rezervării se stabilesc în comunicarea directă cu operatorul / proprietarul, conform procesului descris pe site sau la contact.
      </p>
    </section>

    <section>
      <h2>3. Rezervări și plăți</h2>
      <p>
        Regulile concrete privind avansul, plată, anulare, modificare și politica de rambursare sunt comunicate pentru fiecare proprietate și sejur. Este responsabilitatea ta să verifici aceste condiții înainte de confirmare.
      </p>
    </section>

    <section>
      <h2>4. Comportament și utilizare corectă</h2>
      <ul>
        <li>Nu vei folosi site-ul în mod care încalcă legea sau drepturile terților.</li>
        <li>Nu vei încerca acces neautorizat la sisteme, date sau conturi.</li>
        <li>Informațiile furnizate la rezervare sau contact trebuie să fie corecte și actualizate.</li>
      </ul>
    </section>

    <section>
      <h2>5. Proprietate intelectuală</h2>
      <p>
        Materialele de pe site (texte, imagini, logo, structură) sunt protejate. Reproducerea sau exploatarea comercială fără acord scris nu este permisă, cu excepția utilizării rezonabile în scop personal legat de o rezervare.
      </p>
    </section>

    <section>
      <h2>6. Limitarea răspunderii</h2>
      <p>
        Operatorul depune eforturi pentru ca informațiile să fie corecte și site-ul să fie disponibil, însă nu garantează absența erorilor sau întreruperilor. În măsura permisă de lege, răspunderea pentru prejudicii indirecte sau consecințiale poate fi exclusă sau limitată.
      </p>
    </section>

    <section>
      <h2>7. Modificări</h2>
      <p>
        Ne rezervăm dreptul de a actualiza acești termeni. Versiunea în vigoare este cea publicată pe această pagină, cu data actualizării menționată sus.
      </p>
    </section>

    <section>
      <h2>8. Contact și legislație aplicabilă</h2>
      <p>
        Pentru întrebări legate de acești termeni:
        <a href="mailto:<?= htmlspecialchars(lh_site_contact_email(), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink"><?= htmlspecialchars(lh_site_contact_email(), ENT_QUOTES, 'UTF-8') ?></a>.
        Pentru litigii aplicabilă este legea din jurisdicția relevantă pentru operator (conform înregistrării societății tale).
      </p>
    </section>

    <p class="text-sm border-t border-black/[0.08] pt-8">
      Vezi și
      <a href="<?= htmlspecialchars(lh_public_url('privacy.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4">Politica de confidențialitate</a>
      și
      <a href="<?= htmlspecialchars(lh_public_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4">Contact</a>.
    </p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
