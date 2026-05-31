<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pageTitle = 'Despre noi — Like HOME';
$pageDescription = 'Like HOME: închirieri pe termen scurt cu standard ridicat de confort, proprietăți verificate și comunicare transparentă de la primul click până la check-out.';
$canonicalUrl = lh_absolute_url('about.php');
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3">Brand</p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight">Despre Like HOME</h1>
    <p class="mt-4 text-lg text-ink/80 leading-relaxed max-w-2xl">
    Fondată în 2021, Like Home gestionează peste 50 de apartamente în Chișinău. Construim o experiență simplă și predictibilă pentru fiecare oaspete — de la rezervare până la check-out.
    </p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0 space-y-16">
  <section class="grid md:grid-cols-2 gap-10 md:gap-14 items-start">
    <div>
      <h2 class="text-xl font-bold text-ink mb-4">Misiunea noastră</h2>
      <p class="text-blue-grey leading-relaxed">
      Oferim o gamă largă de apartamente, complet utilate, în cele mai bune zone ale orașului.Rezervare rapidă. Acces ușor. Fără stres.
      </p>
    </div>
    <div class="rounded-3xl bg-gradient-to-br from-logo/90 to-ink p-8 md:p-10 text-white shadow-xl shadow-black/15">
      <h2 class="text-lg font-bold mb-3">Ce înseamnă „premium” pentru noi</h2>
      <ul class="space-y-3 text-sm text-white/85 leading-relaxed">
        <li class="flex gap-3"><span class="text-white/50">·</span> Apartamente complet utilate</li>
        <li class="flex gap-3"><span class="text-white/50">·</span> Self check-in rapid</li>
        <li class="flex gap-3"><span class="text-white/50">·</span> Suport constant</li>
      </ul>
    </div>
  </section>

  <section class="rounded-3xl border border-black/[0.08] bg-white/90 p-8 md:p-10 shadow-sm shadow-black/[0.04]">
    <h2 class="text-xl font-bold text-ink mb-6">Încredere și transparență</h2>
    <div class="grid sm:grid-cols-3 gap-8 text-center sm:text-left">
      <div>
        <div class="text-2xl font-bold text-ink mb-1">50+</div>
        <div class="text-sm text-blue-grey leading-snug">Apartamente gestionate</div>
      </div>
      <div>
        <div class="text-2xl font-bold text-ink mb-1">90%</div>
        <div class="text-sm text-blue-grey leading-snug">Rată medie de ocupare</div>
      </div>
      <div>
        <div class="text-2xl font-bold text-ink mb-1">1000+</div>
        <div class="text-sm text-blue-grey leading-snug">Oaspeți cazați anual</div>
      </div>
    </div>
  </section>

  <section class="rounded-3xl border border-black/[0.08] bg-white/90 p-8 md:p-10 shadow-sm shadow-black/[0.04]">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3">Pentru proprietari</p>
    <h2 class="text-xl font-bold text-ink mb-4">Ai un apartament?</h2>
    <p class="text-blue-grey leading-relaxed max-w-2xl mb-5">
      Îl gestionăm complet și îți optimizăm venitul. Tu te ocupi de restul vieții — noi de rezervări, oaspeți și menținerea apartamentului la standardul Like HOME.
    </p>
    <ul class="space-y-3 text-sm text-blue-grey leading-relaxed max-w-2xl mb-8">
      <li class="flex gap-3"><span class="text-ink/35 shrink-0">·</span> Listare, promovare și prețuri adaptate pieței pentru ocupare mai bună</li>
      <li class="flex gap-3"><span class="text-ink/35 shrink-0">·</span> Rezervări, plată, check-in și suport pentru oaspeți — în grija echipei noastre</li>
      <li class="flex gap-3"><span class="text-ink/35 shrink-0">·</span> Curățenie și mentenanță coordonate între sejururi</li>
      <li class="flex gap-3"><span class="text-ink/35 shrink-0">·</span> Transparență: vezi activitatea și încasările, fără surprize</li>
    </ul>
    <a
      href="<?= htmlspecialchars(lh_public_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>"
      class="inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl text-sm font-semibold bg-cta text-white shadow-md shadow-black/10 hover:brightness-110 transition-all"
    >
      Contactează-ne
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
      </svg>
    </a>
  </section>

  <p class="text-center text-sm text-blue-grey">
    Vrei să ne cunoști mai bine sau ai o întrebare?
    <a href="<?= htmlspecialchars(lh_public_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink">Contactează-ne</a>
    ·
    <a href="<?= htmlspecialchars(lh_public_url('properties.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink">Vezi proprietățile</a>
  </p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
