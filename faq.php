<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pageTitle = 'Întrebări frecvente — Like HOME';

$faqItems = [
    [
        'q' => 'Cum rezerv un apartament în Chișinău?',
        'a' => 'Poți rezerva rapid direct pe site-ul nostru. Alegi perioada, vezi disponibilitatea în timp real și confirmi rezervarea în câteva clickuri. După confirmare, primești toate detaliile pe email sau WhatsApp.',
    ],
    [
        'q' => 'Este sigură rezervarea online?',
        'a' => 'Da. Folosim metode de plată securizate și parteneri de încredere. Datele tale sunt protejate conform standardelor actuale de securitate.',
    ],
    [
        'q' => 'Pot modifica sau anula rezervarea?',
        'a' => 'Da, în funcție de politica aleasă. Oferim opțiuni flexibile, inclusiv anulare gratuită pentru anumite tarife.',
    ],
    [
        'q' => 'La ce oră este check-in și check-out?',
        'a' => 'Check-in: după ora 15:00; Check-out: până la ora 11:00. În funcție de disponibilitate, putem oferi early check-in sau late check-out.',
    ],
    [
        'q' => 'Cum se face check-in-ul la apartament?',
        'a' => 'Toate apartamentele noastre oferă self check-in. Vei primi codul și instrucțiunile înainte de sosire. Acces rapid, fără întâlniri sau așteptare.',
    ],
    [
        'q' => 'Ce documente sunt necesare pentru cazare?',
        'a' => 'Pentru confirmare, solicităm un document de identitate (buletin sau pașaport). Procesul este simplu și durează câteva minute.',
    ],    [
        'q' => 'Cum se face plata pentru cazare?',
        'a' => 'Plata se face online, cu cardul, sau prin platforme precum Booking și Airbnb. În unele cazuri, acceptăm și transfer bancar.',
    ],
    [
        'q' => 'Prețul include toate taxele?',
        'a' => 'Da, prețul afișat include toate costurile principale: cazare, utilități, Wi-Fi, lenjerie și produse de bază.',
    ],
    [
        'q' => 'Există taxe suplimentare?',
        'a' => 'Eventualele taxe (curățenie, oaspeți extra etc.) sunt afișate transparent înainte de rezervare. Fără costuri ascunse.',
    ],
    [
        'q' => 'Ce include un apartament Like Home?',
        'a' => 'Apartamentele sunt complet echipate: bucătărie utilată, Wi-Fi rapid, Smart TV, aer condiționat, mașină de spălat și tot ce ai nevoie pentru un sejur confortabil în Chișinău.',
    ],
    [
      'q' => 'Apartamentele sunt potrivite pentru familii?',
      'a' => 'Da. Majoritatea apartamentelor sunt spațioase și potrivite pentru familii sau grupuri.',
    ],
    [
      'q' => 'Este permis fumatul?',
      'a' => 'Nu. Toate apartamentele sunt non-smoking.',
    ],
    [
      'q' => 'Sunt permise petrecerile sau evenimentele?',
      'a' => 'Nu sunt permise. Menținem un mediu liniștit și confortabil pentru toți oaspeții.',
    ],
    [
      'q' => 'Există parcare la apartament?',
      'a' => 'Parcarea este, în general, publică și depinde de disponibilitate. Unele locații oferă și parcare privată.',
    ],
    [
      'q' => 'Unde sunt situate apartamentele?',
      'a' => 'Avem apartamente în toate zonele importante din Chișinău, inclusiv centru, lângă parcuri, restaurante și zone de interes.',
    ],
    [
      'q' => 'Cum pot contacta echipa Like Home?',
      'a' => 'Suntem disponibili pe WhatsApp, telefon sau email pe toată durata șederii.',
    ],
    [
      'q' => 'Ce se întâmplă dacă apare o problemă?',
      'a' => 'Intervenim rapid. Avem echipă dedicată pentru suport tehnic și asistență.',
    ],
    [
      'q' => 'Oferiți reduceri pentru șederi pe termen lung?',
      'a' => 'Da. Pentru 7+ sau 30+ nopți oferim tarife speciale. Contactează-ne pentru ofertă personalizată.',
    ],

];

$pageDescription = 'Răspunsuri la întrebări frecvente despre rezervări, check-in, plată și politici Like HOME. Pentru cazuri specifice, contactează-ne direct.';
$canonicalUrl = lh_absolute_url('faq.php');

$faqEntities = [];
foreach ($faqItems as $item) {
    $faqEntities[] = [
        '@type' => 'Question',
        'name' => $item['q'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $item['a'],
        ],
    ];
}
$lhJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $faqEntities,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="border-b border-black/[0.06] bg-white/60">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 md:pt-14 pb-6 md:pb-8">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-grey mb-3">Asistență</p>
    <h1 class="text-3xl md:text-4xl font-bold text-ink tracking-tight">Întrebări frecvente</h1>
    <p class="mt-4 text-base text-blue-grey leading-relaxed max-w-xl">
      Răspunsuri la cele mai comune întrebări despre rezervări, plată și sejur. Pentru situații specifice, <a href="<?= htmlspecialchars(lh_public_url('contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-ink font-semibold underline decoration-black/20 underline-offset-4 hover:decoration-ink">scrie-ne direct</a>.
    </p>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 mt-6 md:mt-10 pt-0 pb-0">
  <div class="space-y-3">
    <?php foreach ($faqItems as $item): ?>
      <details class="group rounded-2xl border border-black/[0.08] bg-white/80 shadow-sm shadow-black/[0.03] open:shadow-md open:border-black/12 transition-shadow">
        <summary class="cursor-pointer list-none flex items-center justify-between gap-4 px-5 py-4 md:px-6 md:py-5 text-left font-semibold text-ink text-[15px] md:text-base [&::-webkit-details-marker]:hidden">
          <span><?= htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="shrink-0 w-8 h-8 rounded-full bg-black/[0.04] flex items-center justify-center text-ink/60 group-open:rotate-180 transition-transform" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </span>
        </summary>
        <div class="px-5 pb-5 md:px-6 md:pb-6 pt-0 text-sm md:text-[15px] text-blue-grey leading-relaxed border-t border-black/[0.06]">
          <p class="pt-4"><?= htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
