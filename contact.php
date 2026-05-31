<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/site_nav.php';

$pageTitle = 'Contact — Like HOME';
$pageDescription = 'Contact Like HOME pentru rezervări, întrebări despre proprietăți sau asistență în timpul sejurului. Răspundem de obicei în cursul unei zile lucrătoare.';
$canonicalUrl = lh_absolute_url('contact.php');

$contactEmail = lh_site_contact_email();
$contactCity = lh_site_contact_city();
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3">Suntem aici</p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight">Contact</h1>
    <p class="mt-4 text-base text-blue-grey leading-relaxed max-w-xl">
      Pentru rezervări, întrebări despre o proprietate sau asistență în timpul sejurului, scrie-ne pe email. Răspundem de obicei în decursul unei zile lucrătoare.
    </p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0">
  <div class="grid md:grid-cols-2 gap-8 md:gap-10">
    <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>" class="group rounded-3xl border border-black/[0.08] bg-white p-8 shadow-sm shadow-black/[0.04] hover:border-black/15 hover:shadow-md transition-all">
      <div class="text-xs font-semibold uppercase tracking-widest text-blue-grey mb-2">Scrie-ne</div>
      <div class="text-lg font-semibold text-ink group-hover:underline decoration-black/20 underline-offset-4"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></div>
      <p class="mt-3 text-sm text-blue-grey leading-relaxed">Deschide clientul tău de email sau copiază adresa pentru o cerere detaliată (perioadă, număr persoane, proprietate).</p>
    </a>
    <div class="rounded-3xl border border-black/[0.08] bg-surface p-8 shadow-sm shadow-black/[0.04]">
      <div class="text-xs font-semibold uppercase tracking-widest text-blue-grey mb-2">Locație</div>
      <div class="text-lg font-semibold text-ink"><?= htmlspecialchars($contactCity, ENT_QUOTES, 'UTF-8') ?></div>
      <p class="mt-3 text-sm text-blue-grey leading-relaxed">Operăm proprietăți în zonă; adresele exacte și instrucțiunile de check-in le primești după confirmarea rezervării.</p>
    </div>
  </div>

  <div class="mt-10 rounded-2xl bg-black/[0.03] border border-black/[0.06] px-6 py-5 text-sm text-blue-grey text-center md:text-left">
    <span class="font-semibold text-ink">Timp de răspuns:</span> luni–vineri, în ordinea mesajelor primite. Pentru urgențe în timpul sejurului, menționează în subiect „Urgent” și numărul rezervării.
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
