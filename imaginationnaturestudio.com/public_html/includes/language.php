<?php
/**
 * Imagination Nature Studio — Public Website Language Engine
 * Supported: English (en), Kinyarwanda (rw), Français (fr)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

function site_supported_languages(): array
{
    return [
        'en' => 'English',
        'rw' => 'Kinyarwanda',
        'fr' => 'Français',
    ];
}

function site_lang(): string
{
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved;
    }

    $supported = site_supported_languages();
    $selected = null;

    // 1) Explicit language selection from the visitor.
    if (isset($_GET['lang']) && is_string($_GET['lang']) && isset($supported[$_GET['lang']])) {
        $selected = $_GET['lang'];
    }

    // 2) Existing session.
    if ($selected === null && !empty($_SESSION['site_lang']) && isset($supported[$_SESSION['site_lang']])) {
        $selected = $_SESSION['site_lang'];
    }

    // 3) Existing cookie.
    if ($selected === null && !empty($_COOKIE['ins_site_lang']) && isset($supported[$_COOKIE['ins_site_lang']])) {
        $selected = $_COOKIE['ins_site_lang'];
    }

    // 4) Default.
    if ($selected === null) {
        $selected = 'en';
    }

    // Save only once per request.
    $_SESSION['site_lang'] = $selected;

    if (!isset($_COOKIE['ins_site_lang']) || $_COOKIE['ins_site_lang'] !== $selected) {
        setcookie('ins_site_lang', $selected, [
            'expires'  => time() + (60 * 60 * 24 * 365),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['ins_site_lang'] = $selected;
    }

    $resolved = $selected;
    return $resolved;
}

function site_translations(): array
{
    static $translations = null;

    if ($translations !== null) {
        return $translations;
    }

    $translations = [
        'en' => [
            'home' => 'Home',
            'about' => 'About',
            'services' => 'Services',
            'portfolio' => 'Portfolio',
            'contact' => 'Contact',
            'book_session' => 'Book a Session',
            'language_label' => 'Language',
            'choose_language' => 'Choose language',
            'tap_to_order' => 'Tap to order',
            'special_offer' => 'SPECIAL OFFER',
            'save' => 'SAVE',
            'seo_home_desc' => 'Creative wedding, portrait, maternity, family, event, editorial, and commercial photography in Kigali, Rwanda. Book',

            'days' => 'Days',
            'hours' => 'Hours',
            'min' => 'Min',
            'sec' => 'Sec',
            'book_now' => 'Book Now',
            'whatsapp_us' => 'WhatsApp Us',
            'limited_offer' => 'Limited-time offer • Subject to availability',
            'previous_promotion' => 'Previous promotion',
            'next_promotion' => 'Next promotion',
            'promotion' => 'Promotion',
            'weddings' => 'Weddings',
            'portraits' => 'Portraits',
            'maternity' => 'Maternity',
            'family' => 'Family',
            'editorial' => 'Editorial',
            'events' => 'Events',
            'commercial' => 'Commercial',
            'kigali_rwanda' => 'Kigali · Rwanda',
            'from' => 'From',
            'book' => 'Book',
            'view_all_services' => 'View all services',
            'why_choose_us' => 'Why Choose Us',
            'more_than_photography' => 'More Than Photography.<br>We Create Memories.',
            'why_intro' => 'At Imagination Nature Studio, we combine creativity, professional equipment, careful editing, and personal service to turn important moments into lasting memories.',
            'professional_photography' => 'Professional Photography',
            'professional_photography_desc' => 'Carefully crafted images for portraits, weddings, events, maternity, family, fashion, and commercial work.',
            'photo_video' => 'Photo & Video',
            'photo_video_desc' => 'Capture your story with professional photography and cinematic video coverage for your most important occasions.',
            'premium_printing_frames' => 'Premium Printing & Frames',
            'premium_printing_frames_desc' => 'Turn your favorite photographs into beautiful prints, frames, photobooks, and artwork you can keep for years.',
            'fast_delivery' => 'Fast Delivery',
            'fast_delivery_desc' => 'We value your time and provide efficient service and fast turnaround on selected photography and printing projects.',
            'professional_retouching' => 'Professional Retouching',
            'professional_retouching_desc' => 'Natural, detailed editing that preserves the character of your photographs while giving them a polished professional finish.',
            'personalized_service' => 'Personalized Service',
            'personalized_service_desc' => 'From your first booking to final delivery, we work with you to understand your vision and create images that feel personal.',
            'book_your_session' => 'Book Your Session',
            'explore_services' => 'Explore Our Services',
            'client_reviews' => 'Client Reviews',
            'what_clients_say' => 'What Our Clients Say',
            'reviews_intro' => 'Every session is an opportunity to create an experience worth remembering. Here are a few words from clients who have experienced our work.',
            'google_review' => 'Google Review',
            'review_grace' => 'I had an amazing experience at Imagination Nature Studio. They offer the best professional photography in Kigali with excellent customer service. The photos were high quality, editing was fast, and I highly recommend this photo studio in Kigali.',
            'review_guillaume' => 'Such amazing work, experience and talented with good heart.',
            'review_adeline' => 'Studio Photo experience',
            'reviews_cta' => "Have you worked with us? We'd love to hear about your experience on Google.",
            'leave_google_review' => 'Leave a Google Review',
            'share_experience' => 'Share your experience with us',
            'read_reviews' => 'Read our reviews',
            'visit_studio' => 'Visit Our Studio',
            'find_studio' => 'Find Imagination Nature Studio',
            'studio_intro' => 'Come visit us in Kigali, Rwanda. Get directions directly to our studio and plan your visit with ease.',
            'get_directions' => 'Get Directions',
            'portfolio_image' => 'Portfolio image',
            'view_full_portfolio' => 'View full portfolio',
            'close' => 'Close',
        ],
        'rw' => [
            'home' => 'Ahabanza',
            'about' => 'Abo turi bo',
            'services' => 'Serivisi',
            'portfolio' => 'Portfolio',
            'contact' => 'Twandikire',
            'book_session' => 'Bika gahunda',
            'language_label' => 'Ururimi',
            'choose_language' => 'Hitamo ururimi',
            'tap_to_order' => 'Kanda hano ubisabe',
            'special_offer' => 'PROMOTION YIHARIYE',
            'save' => 'BUNGUKA',
            'seo_home_desc' => 'Gufotora ubukwe, portraits, maternity, imiryango, events, editorial n’ubucuruzi i Kigali, Rwanda. Bika',
            'days' => 'Iminsi',
            'hours' => 'Amasaha',
            'min' => 'Min',
            'sec' => 'Sec',
            'book_now' => 'Bika ubu',
            'whatsapp_us' => 'Twandikire kuri WhatsApp',
            'limited_offer' => 'Iyi promotion ifite igihe gito • Biterwa n’uko serivisi iboneka',
            'previous_promotion' => 'Promotion ibanza',
            'next_promotion' => 'Promotion ikurikira',
            'promotion' => 'Promotion',
            'weddings' => 'Ubukwe',
            'portraits' => 'Amafoto y’abantu',
            'maternity' => 'Ababyeyi batwite',
            'family' => 'Imiryango',
            'editorial' => 'Editorial',
            'events' => 'Events',
            'commercial' => 'Ubucuruzi',
            'kigali_rwanda' => 'Kigali · Rwanda',
            'from' => 'Guhera kuri',
            'book' => 'Bika',
            'view_all_services' => 'Reba serivisi zose',
            'why_choose_us' => 'Kuki Mutugana',
            'more_than_photography' => 'Turenga Gufotora.<br>Turema Urwibutso.',
            'why_intro' => 'Muri Imagination Nature Studio, duhuza ubuhanzi, ibikoresho by’umwuga, gutunganya amafoto neza n’ubwitange mu kwakira abakiriya kugira ngo ibihe by’ingenzi bibashe guhinduka urwibutso rurambye.',
            'professional_photography' => 'Gufotora k’umwuga',
            'professional_photography_desc' => 'Amafoto ateguwe neza ku bantu, ubukwe, events, ababyeyi batwite, imiryango, fashion n’imishinga y’ubucuruzi.',
            'photo_video' => 'Amafoto & Video',
            'photo_video_desc' => 'Tubika inkuru yawe dukoresheje amafoto y’umwuga na video imeze nka cinema ku bihe byawe by’ingenzi.',
            'premium_printing_frames' => 'Gucapa & Frames z’Ubwiza',
            'premium_printing_frames_desc' => 'Hindura amafoto ukunda abe prints nziza, frames, photobooks n’ibikorwa by’ubuhanzi uzagumana imyaka myinshi.',
            'fast_delivery' => 'Gutanga vuba',
            'fast_delivery_desc' => 'Twubaha igihe cyawe kandi dutanga serivisi yihuse no kurangiza vuba imishinga y’amafoto no gucapa.',
            'professional_retouching' => 'Gutunganya Amafoto k’Umwuga',
            'professional_retouching_desc' => 'Gutunganya amafoto mu buryo busanzwe kandi burambuye, tugakomeza isura n’imiterere y’ifoto ariko tukayiha umwimerere w’umwuga.',
            'personalized_service' => 'Serivisi Ihariye kuri Wowe',
            'personalized_service_desc' => 'Kuva ubukije kugeza uhawe amafoto ya nyuma, dukorana nawe kugira ngo twumve icyo ushaka kandi tureme amafoto akubereye.',
            'book_your_session' => 'Bika Gahunda Yawe',
            'explore_services' => 'Reba Serivisi Zacu',
            'client_reviews' => 'Ibitekerezo by’Abakiriya',
            'what_clients_say' => 'Ibyo Abakiriya Bacu Bavuga',
            'reviews_intro' => 'Buri session ni amahirwe yo gukora ubunararibonye butazibagirana. Dore bimwe mu bitekerezo by’abakiriya bakoranye natwe.',
            'google_review' => 'Google Review',
            'review_grace' => 'Nagize ubunararibonye bwiza cyane muri Imagination Nature Studio. Batanga amafoto y’umwuga meza cyane i Kigali kandi bakakira abakiriya neza. Amafoto yari afite quality nziza, editing yakozwe vuba, kandi ndabasaba cyane ku muntu ushaka studio y’amafoto i Kigali.',
            'review_guillaume' => 'Akazi keza cyane, ubunararibonye bwiza kandi bafite impano n’umutima mwiza.',
            'review_adeline' => 'Ubunararibonye bwa Studio Photo',
            'reviews_cta' => 'Waba warakoranye natwe? Twakwishimira kumva ubunararibonye bwawe kuri Google.',
            'leave_google_review' => 'Siga Google Review',
            'share_experience' => 'Sangiza abandi ubunararibonye bwawe',
            'read_reviews' => 'Soma reviews zacu',
            'visit_studio' => 'Sura Studio Yacu',
            'find_studio' => 'Shaka Imagination Nature Studio',
            'studio_intro' => 'Ngwino udusure i Kigali, Rwanda. Shaka inzira igana kuri studio yacu kandi utegure uruzinduko rwawe byoroshye.',
            'get_directions' => 'Shaka Inzira',
            'portfolio_image' => 'Ifoto ya portfolio',
            'view_full_portfolio' => 'Reba portfolio yose',
            'close' => 'Funga',
        ],
        'fr' => [
            'Stories you can feel.' => 'Des histoires que vous pouvez ressentir.',
            'Stories you can' => 'Des histoires que vous pouvez',
            'feel.' => 'ressentir.',
            'Artful photography for people who want more than a pose—honest emotion, confident direction, and images that stay alive for generations.' => 'Une photographie artistique pour ceux qui veulent plus qu’une simple pose—des émotions sincères, une direction assurée et des images qui restent vivantes pendant des générations.',
            'Explore the stories' => 'Découvrez les histoires',
            'Start your experience' => 'Commencez votre expérience',
            'Our Work' => 'Notre travail',
            'Our work' => 'Notre travail',
            'Considered frames.' => 'Des cadrages réfléchis.',
            'Unscripted feeling.' => 'Des émotions spontanées.',
            'Love, naturally composed' => 'L’amour, naturellement composé',
            'home' => 'Accueil',
            'about' => 'À propos',
            'services' => 'Services',
            'portfolio' => 'Portfolio',
            'contact' => 'Contact',
            'book_session' => 'Réserver une séance',
            'language_label' => 'Langue',
            'choose_language' => 'Choisir la langue',
            'tap_to_order' => 'Appuyez pour commander',
            'special_offer' => 'OFFRE SPÉCIALE',
            'save' => 'ÉCONOMISEZ',
            'seo_home_desc' => 'Photographie de mariage, portrait, maternité, famille, événement, éditoriale et commerciale à Kigali, Rwanda. Réservez',
            'days' => 'Jours',
            'hours' => 'Heures',
            'min' => 'Min',
            'sec' => 'Sec',
            'book_now' => 'Réserver maintenant',
            'whatsapp_us' => 'Nous contacter sur WhatsApp',
            'limited_offer' => 'Offre à durée limitée • Sous réserve de disponibilité',
            'previous_promotion' => 'Promotion précédente',
            'next_promotion' => 'Promotion suivante',
            'promotion' => 'Promotion',
            'weddings' => 'Mariages',
            'portraits' => 'Portraits',
            'maternity' => 'Maternité',
            'family' => 'Famille',
            'editorial' => 'Éditorial',
            'events' => 'Événements',
            'commercial' => 'Commercial',
            'kigali_rwanda' => 'Kigali · Rwanda',
            'from' => 'À partir de',
            'book' => 'Réserver',
            'view_all_services' => 'Voir tous les services',
            'why_choose_us' => 'Pourquoi Nous Choisir',
            'more_than_photography' => 'Plus que de la photographie.<br>Nous créons des souvenirs.',
            'why_intro' => 'Chez Imagination Nature Studio, nous associons créativité, équipement professionnel, retouche soignée et service personnalisé pour transformer les moments importants en souvenirs durables.',
            'professional_photography' => 'Photographie Professionnelle',
            'professional_photography_desc' => 'Des images soigneusement réalisées pour les portraits, mariages, événements, maternité, famille, mode et projets commerciaux.',
            'photo_video' => 'Photo & Vidéo',
            'photo_video_desc' => 'Racontez votre histoire avec une photographie professionnelle et une couverture vidéo cinématographique de vos moments les plus importants.',
            'premium_printing_frames' => 'Impression & Cadres Premium',
            'premium_printing_frames_desc' => 'Transformez vos photographies préférées en beaux tirages, cadres, photobooks et œuvres d’art à conserver pendant des années.',
            'fast_delivery' => 'Livraison Rapide',
            'fast_delivery_desc' => 'Nous respectons votre temps et proposons un service efficace ainsi qu’une réalisation rapide pour certains projets de photographie et d’impression.',
            'professional_retouching' => 'Retouche Professionnelle',
            'professional_retouching_desc' => 'Une retouche naturelle et détaillée qui préserve le caractère de vos photographies tout en leur donnant une finition professionnelle.',
            'personalized_service' => 'Service Personnalisé',
            'personalized_service_desc' => 'De votre première réservation à la livraison finale, nous travaillons avec vous pour comprendre votre vision et créer des images qui vous ressemblent.',
            'book_your_session' => 'Réserver Votre Séance',
            'explore_services' => 'Découvrir Nos Services',
            'client_reviews' => 'Avis des Clients',
            'what_clients_say' => 'Ce Que Disent Nos Clients',
            'reviews_intro' => 'Chaque séance est une occasion de créer une expérience mémorable. Voici quelques mots de clients qui ont découvert notre travail.',
            'google_review' => 'Avis Google',
            'review_grace' => 'J’ai vécu une expérience incroyable chez Imagination Nature Studio. Ils proposent l’une des meilleures photographies professionnelles à Kigali avec un excellent service client. Les photos étaient de grande qualité, la retouche rapide, et je recommande vivement ce studio photo à Kigali.',
            'review_guillaume' => 'Un travail exceptionnel, une très belle expérience et beaucoup de talent avec un grand cœur.',
            'review_adeline' => 'Expérience Studio Photo',
            'reviews_cta' => 'Vous avez travaillé avec nous ? Nous aimerions connaître votre expérience sur Google.',
            'leave_google_review' => 'Laisser un avis Google',
            'share_experience' => 'Partagez votre expérience avec nous',
            'read_reviews' => 'Lire nos avis',
            'visit_studio' => 'Visitez Notre Studio',
            'find_studio' => 'Trouver Imagination Nature Studio',
            'studio_intro' => 'Venez nous rendre visite à Kigali, Rwanda. Obtenez directement l’itinéraire vers notre studio et planifiez facilement votre visite.',
            'get_directions' => 'Itinéraire',
            'portfolio_image' => 'Image du portfolio',
            'view_full_portfolio' => 'Voir tout le portfolio',
            'close' => 'Fermer',
        ],
    ];

    return $translations;
}

function __(string $key, ?string $lang = null): string
{
    $lang = $lang ?: site_lang();
    $all = site_translations();

    return $all[$lang][$key] ?? $all['en'][$key] ?? $key;
}

function lang_url(string $lang): string
{
    $supported = site_supported_languages();

    if (!isset($supported[$lang])) {
        $lang = 'en';
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);

    $path = $parts['path'] ?? '/';
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $query['lang'] = $lang;

    return $path . '?' . http_build_query($query);
}

function clean_language_url(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        unset($query['lang']);
    }

    return $path . ($query ? '?' . http_build_query($query) : '');
}

/* =========================================================
   DATABASE CONTENT TRANSLATION HELPERS
   ========================================================= */

function localize_row(array $row, string $baseFields = '', ?string $lang = null): array
{
    $lang = $lang ?: site_lang();

    if ($lang === 'en') {
        return $row;
    }

    $suffix = $lang === 'rw' ? '_rw' : '_fr';

    $fields = $baseFields !== ''
        ? array_filter(array_map('trim', explode(',', $baseFields)))
        : [];

    foreach ($fields as $field) {
        $translated = $field . $suffix;

        if (array_key_exists($translated, $row) && trim((string)$row[$translated]) !== '') {
            $row[$field] = $row[$translated];
        } elseif (array_key_exists($field, $row) && trim((string)$row[$field]) !== '') {
            // If a database translation is not filled yet, use the built-in
            // phrase dictionary for known public-site phrases.
            $row[$field] = translate_text((string)$row[$field], $lang);
        }
    }

    return $row;
}

function localize_home_section(?array $row): ?array
{
    if (!$row) {
        return null;
    }

    return localize_row(
        $row,
        'title,subtitle,content,button_text,button_text_2'
    );
}

function localize_services(array $rows): array
{
    $out = [];

    foreach ($rows as $row) {
        $out[] = localize_row($row, 'name,description');
    }

    return $out;
}

function localize_promotion(array $row): array
{
    return localize_row(
        $row,
        'title,subtitle,description,button_text'
    );
}

function localize_promotions(array $rows): array
{
    $out = [];

    foreach ($rows as $row) {
        $out[] = localize_promotion($row);
    }

    return $out;
}

function localize_packages(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[] = localize_row($row, 'name,description');
    }
    return $out;
}

function localize_gallery(array $rows): array
{
    $out = [];

    foreach ($rows as $row) {
        $out[] = localize_row($row, 'title,caption,description');
    }

    return $out;
}


/* =========================================================
   STEP 5 — PUBLIC SITE COMPATIBILITY + STATIC PHRASE ENGINE
   ========================================================= */

function ims_supported_languages(): array
{
    $out = [];
    foreach (site_supported_languages() as $code => $name) {
        $native = $name;
        if ($code === 'rw') $native = 'Kinyarwanda';
        if ($code === 'fr') $native = 'Français';
        $flags = ['en' => '🇬🇧', 'rw' => '🇷🇼', 'fr' => '🇫🇷'];
        $out[$code] = [
            'name'   => $name,
            'native' => $native,
            'html'   => $code === 'rw' ? 'rw' : $code,
            'flag'   => $flags[$code] ?? '🌐',
        ];
    }
    return $out;
}

function current_language(): string
{
    return site_lang();
}

function language_info(): array
{
    $code = site_lang();
    $all = ims_supported_languages();
    return $all[$code] ?? $all['en'];
}

function language_url(string $lang): string
{
    return lang_url($lang);
}

function translate_text(string $text, ?string $lang = null): string
{
    $lang = $lang ?: site_lang();
    if ($lang === 'en') return $text;

    $map = ims_phrase_translations($lang);
    return $map[$text] ?? $text;
}

function t(string $text, ?string $fallback = null): string
{
    $translated = translate_text($text);
    return $translated !== $text ? $translated : ($fallback ?? $text);
}

function ims_phrase_translations(string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];

    $cache[$lang] = [];

    /* First reuse the large public dictionary already maintained above. */
    $all = site_translations();
    foreach (($all['en'] ?? []) as $key => $english) {
        if (isset($all[$lang][$key]) && $english !== $all[$lang][$key]) {
            $cache[$lang][$english] = $all[$lang][$key];
        }
    }

    $extra = [
        'rw' => [
            'Stories you can feel.' => 'Inkuru ushobora kumva.',
            'Stories you can' => 'Inkuru ushobora',
            'feel.' => 'kumva.',
            'Artful photography for people who want more than a pose—honest emotion, confident direction, and images that stay alive for generations.' => 'Gufotora mu buryo bw’ubuhanzi ku bantu bashaka ibirenze ifoto isanzwe—amarangamutima y’ukuri, kuyoborwa neza, n’amafoto akomeza kubaho mu bisekuru.',
            'Explore the stories' => 'Reba inkuru',
            'Start your experience' => 'Tangira ubunararibonye bwawe',
            'Our Work' => 'Ibikorwa byacu',
            'Our work' => 'Ibikorwa byacu',
            'Considered frames.' => 'Amafoto ateguwe neza.',
            'Unscripted feeling.' => 'Amarangamutima y’ukuri.',
            'Love, naturally composed' => 'Urukundo rufashwe mu buryo busanzwe',
            'About us' => 'Abo turi bo',
            'About · Imagination Nature Studio' => 'Abo turi bo · Imagination Nature Studio',
            'Photography with intent, in the heart of Kigali.' => 'Gufotora bifite intego, mu mutima wa Kigali.',
            'Imagination Nature Studio is a Kigali-based photography and creative studio founded by Tuyikunde Regis. We craft images with intention—elegant, honest, and lasting.' => 'Imagination Nature Studio ni studio yo gufotora no guhanga ibikorwaremezo iri i Kigali, yashinzwe na Tuyikunde Regis. Dufata amafoto afite intego—meza, y’ukuri kandi arambye.',
            'From weddings in Kiyovu to editorial work across Rwanda, our practice is rooted in story, craft and genuine care for the people in front of our lens.' => 'Kuva ku bukwe bwo muri Kiyovu kugeza ku mirimo ya editorial hirya no hino mu Rwanda, akazi kacu gashingira ku nkuru, ubuhanga no kwita ku bantu turi gufotora.',
            'Services' => 'Serivisi',
            'Services · Imagination Nature Studio' => 'Serivisi · Imagination Nature Studio',
            'Everything we offer.' => 'Ibyo dutanga byose.',
            'From intimate portraits to grand weddings, our studio crafts imagery with intention.' => 'Kuva ku mafoto y’abantu ku giti cyabo kugeza ku bukwe bunini, studio yacu ikora amafoto afite intego.',
            'No services available yet.' => 'Nta serivisi ziraboneka.',
            'On request' => 'Ku busabe',
            'Book This Service' => 'Bika iyi serivisi',
            'Duration:' => 'Igihe:',
            'Portfolio · Imagination Nature Studio' => 'Portfolio · Imagination Nature Studio',
            'Selected stories.' => 'Inkuru twahisemo.',
            'All' => 'Byose',
            'Weddings' => 'Ubukwe',
            'Portraits' => 'Amafoto y’abantu',
            'Editorial' => 'Editorial',
            'Maternity' => 'Ababyeyi batwite',
            'Family' => 'Imiryango',
            'Events' => 'Events',
            'Commercial' => 'Ubucuruzi',
            'Portfolio image' => 'Ifoto ya portfolio',
            'Contact · Imagination Nature Studio' => 'Twandikire · Imagination Nature Studio',
            'Let\'s talk.' => 'Tuganire.',
            'Thank you — your message has been received.' => 'Murakoze — ubutumwa bwanyu twabwakiriye.',
            'Please enter your name, a valid email and a message.' => 'Andika amazina yawe, email ikora n’ubutumwa.',
            'Studio' => 'Studio',
            'Phone' => 'Telefone',
            'Email' => 'Email',
            'Hours' => 'Amasaha',
            'Social' => 'Imbuga nkoranyambaga',
            'WhatsApp us' => 'Twandikire kuri WhatsApp',
            'Name *' => 'Amazina *',
            'Email *' => 'Email *',
            'Phone' => 'Telefone',
            'Subject' => 'Umutwe w’ubutumwa',
            'Message *' => 'Ubutumwa *',
            'Send message' => 'Ohereza ubutumwa',
            'Explore' => 'Reba byinshi',
            'Follow' => 'Dukurikire',
            'Book Your Session' => 'Bika Gahunda Yawe',
            'Book a Session' => 'Bika gahunda',
            'Book a Session · Imagination Nature Studio' => 'Bika gahunda · Imagination Nature Studio',
            'Book Your Session' => 'Bika Gahunda Yawe',
            'Tell us about your photography session or event. Our team will review your booking and contact you to confirm the appointment.' => 'Tubwire ibijyanye na gahunda yo gufotora cyangwa event yawe. Itsinda ryacu rizasuzuma booking yawe maze rikuvugishe kugira ngo ryemeze gahunda.',
            'Choose your service' => 'Hitamo serivisi yawe',
            'Service *' => 'Serivisi *',
            '— Select a service —' => '— Hitamo serivisi —',
            'Package' => 'Package',
            '— No package / custom —' => '— Nta package / iyihariye —',
            'Packages are loaded according to the selected service.' => 'Packages zishyirwa hano hakurikijwe serivisi wahisemo.',
            'Date & time' => 'Itariki n’igihe',
            'Preferred date *' => 'Itariki wifuza *',
            'Number of people *' => 'Umubare w’abantu *',
            'Available start times *' => 'Amasaha yo gutangira aboneka *',
            'Select a date to see available times.' => 'Hitamo itariki kugira ngo ubone amasaha aboneka.',
            'End time *' => 'Igihe cyo kurangiza *',
            'Event type' => 'Ubwoko bwa event',
            'Wedding bookings can contain more than one event day. Add every date included in the wedding package below.' => 'Booking y’ubukwe ishobora kugira iminsi irenze umwe. Ongeramo buri tariki iri muri package y’ubukwe hano hasi.',
            'Wedding Day' => 'Umunsi w’ubukwe',
            'Remove' => 'Kuraho',
            'Date *' => 'Itariki *',
            'Event / Day Type *' => 'Ubwoko bw’umunsi / Event *',
            'Start time *' => 'Igihe cyo gutangira *',
            'End time *' => 'Igihe cyo kurangiza *',
            '+ Add another wedding date' => '+ Ongeramo indi tariki y’ubukwe',
            'Your details' => 'Amakuru yawe',
            'Full name *' => 'Amazina yose *',
            'Phone number *' => 'Numero ya telefone *',
            'WhatsApp' => 'WhatsApp',
            'Your location *' => 'Aho uherereye *',
            'Company' => 'Ikigo',
            'Event location' => 'Aho event izabera',
            'Additional notes' => 'Andi makuru',
            'Payment' => 'Kwishyura',
            '30% deposit required.' => 'Harasabwa avance ya 30%.',
            'A minimum deposit of 30% of the booking total is required when submitting your booking request.' => 'Iyo utanze booking hasabwa avance nibura ingana na 30% by’amafaranga yose.',
            'Payment method *' => 'Uburyo bwo kwishyura *',
            '— Select payment method —' => '— Hitamo uburyo bwo kwishyura —',
            'Cash' => 'Cash',
            'Mobile Money' => 'Mobile Money',
            'Bank Transfer' => 'Kohereza kuri banki',
            'Other' => 'Ubundi',
            'Amount paid (RWF) *' => 'Amafaranga wishyuye (RWF) *',
            'Select a service to calculate the required 30% deposit.' => 'Hitamo serivisi kugira ngo tubare avance ya 30% isabwa.',
            'Total' => 'Igiteranyo',
            'Required deposit (30%)' => 'Avance isabwa (30%)',
            'Amount paid' => 'Amafaranga wishyuye',
            'Remaining balance' => 'Asigaye kwishyura',
            'Submit Booking Request' => 'Ohereza booking',
            'Your booking will first be submitted as' => 'Booking yawe izabanza koherezwa nka',
            'Pending' => 'Tegereje kwemezwa',
            'Our team will review it and confirm it. Once confirmed, your invoice will become available.' => 'Itsinda ryacu rizayisuzuma kandi riyemeze. Iyo imaze kwemezwa, invoice yawe izaboneka.',
            'Booking Confirmation' => 'Kwemeza Booking',
            'Your booking number is' => 'Numero ya booking yawe ni',
            'Confirmation' => 'Kwemezwa',
            'Your booking is approved' => 'Booking yawe yemejwe',
            'Your booking has been confirmed by Imagination Nature Studio. We look forward to capturing your moments.' => 'Booking yawe yemejwe na Imagination Nature Studio. Twishimiye gufata ibihe byawe by’ingenzi.',
            'This booking was cancelled' => 'Iyi booking yahagaritswe',
            'Contact us if this was unexpected.' => 'Twandikire niba ibi bitari biteganyijwe.',
            'Awaiting confirmation' => 'Tegereje kwemezwa',
            'Your booking request has been received. Our team will review it and confirm the appointment.' => 'Booking yawe twarayakiriye. Itsinda ryacu rizayisuzuma kandi ryemeze gahunda.',
            'Your invoice will become available after your booking is approved.' => 'Invoice yawe izaboneka booking imaze kwemezwa.',
            'Checking booking status automatically…' => 'Turimo kugenzura status ya booking mu buryo bwikora…',
            'Your Invoice' => 'Invoice Yawe',
            'Your booking has been approved. Your invoice is now available.' => 'Booking yawe yemejwe. Invoice yawe ubu iraboneka.',
            'Download Invoice' => 'Kuramo Invoice',
            'Amount due & payment instructions' => 'Amafaranga asigaye n’amabwiriza yo kwishyura',
            'A receipt will be issued by our team once your payment is confirmed.' => 'Receipt izatangwa n’itsinda ryacu nyuma yo kwemeza ubwishyu bwawe.',
            'Your invoice is being prepared.' => 'Invoice yawe irategurwa.',
            'Pay with MoMo' => 'Wishyure kuri MoMo',
            'Scan the QR code below to make your payment.' => 'Scan QR code iri hasi kugira ngo wishyure.',
            'After payment, send proof to WhatsApp' => 'Nyuma yo kwishyura, ohereza gihamya kuri WhatsApp',
            'with your booking number' => 'hamwe na numero ya booking yawe',
            'A confirmation email has been sent to' => 'Email yo kwemeza yoherejwe kuri',
            'Send via WhatsApp' => 'Ohereza kuri WhatsApp',
            'Back to home' => 'Subira ahabanza',
            'Invalid Promotion' => 'Promotion itemewe',
            'Promotion not found' => 'Promotion ntiyabonetse',
            'The promotion link is invalid.' => 'Link ya promotion ntiyemewe.',
            'Go to Website' => 'Jya ku rubuga',
            'Promotion Not Found' => 'Promotion ntiyabonetse',
            'This promotion may have been removed or is no longer available.' => 'Iyi promotion ishobora kuba yarakuweho cyangwa itakiboneka.',
            'WhatsApp Unavailable' => 'WhatsApp ntiboneka',
            'This promotion does not have a WhatsApp number configured yet.' => 'Iyi promotion nta nimero ya WhatsApp yashyizwemo.',
            'Back to Website' => 'Subira ku rubuga',
        ],
        'fr' => [
            'About us' => 'À propos de nous',
            'About · Imagination Nature Studio' => 'À propos · Imagination Nature Studio',
            'Photography with intent, in the heart of Kigali.' => 'La photographie avec intention, au cœur de Kigali.',
            'Imagination Nature Studio is a Kigali-based photography and creative studio founded by Tuyikunde Regis. We craft images with intention—elegant, honest, and lasting.' => 'Imagination Nature Studio est un studio de photographie et de création basé à Kigali, fondé par Tuyikunde Regis. Nous créons des images avec intention—élégantes, authentiques et durables.',
            'From weddings in Kiyovu to editorial work across Rwanda, our practice is rooted in story, craft and genuine care for the people in front of our lens.' => 'Des mariages à Kiyovu aux projets éditoriaux à travers le Rwanda, notre travail repose sur l’histoire, le savoir-faire et une attention sincère aux personnes devant notre objectif.',
            'Services' => 'Services',
            'Services · Imagination Nature Studio' => 'Services · Imagination Nature Studio',
            'Everything we offer.' => 'Tout ce que nous proposons.',
            'From intimate portraits to grand weddings, our studio crafts imagery with intention.' => 'Des portraits intimes aux grands mariages, notre studio crée des images avec intention.',
            'No services available yet.' => 'Aucun service disponible pour le moment.',
            'On request' => 'Sur demande',
            'Book This Service' => 'Réserver ce service',
            'Duration:' => 'Durée :',
            'Portfolio · Imagination Nature Studio' => 'Portfolio · Imagination Nature Studio',
            'Selected stories.' => 'Histoires sélectionnées.',
            'All' => 'Tout',
            'Weddings' => 'Mariages',
            'Portraits' => 'Portraits',
            'Editorial' => 'Éditorial',
            'Maternity' => 'Maternité',
            'Family' => 'Famille',
            'Events' => 'Événements',
            'Commercial' => 'Commercial',
            'Portfolio image' => 'Image du portfolio',
            'Contact · Imagination Nature Studio' => 'Contact · Imagination Nature Studio',
            'Let\'s talk.' => 'Parlons-en.',
            'Thank you — your message has been received.' => 'Merci — votre message a bien été reçu.',
            'Please enter your name, a valid email and a message.' => 'Veuillez saisir votre nom, une adresse e-mail valide et un message.',
            'Studio' => 'Studio',
            'Phone' => 'Téléphone',
            'Email' => 'E-mail',
            'Hours' => 'Horaires',
            'Social' => 'Réseaux sociaux',
            'WhatsApp us' => 'Écrivez-nous sur WhatsApp',
            'Name *' => 'Nom *',
            'Email *' => 'E-mail *',
            'Subject' => 'Objet',
            'Message *' => 'Message *',
            'Send message' => 'Envoyer le message',
            'Explore' => 'Explorer',
            'Follow' => 'Suivez-nous',
            'Book Your Session' => 'Réserver votre séance',
            'Book a Session' => 'Réserver une séance',
            'Book a Session · Imagination Nature Studio' => 'Réserver une séance · Imagination Nature Studio',
            'Tell us about your photography session or event. Our team will review your booking and contact you to confirm the appointment.' => 'Parlez-nous de votre séance photo ou de votre événement. Notre équipe examinera votre réservation et vous contactera pour confirmer le rendez-vous.',
            'Choose your service' => 'Choisissez votre service',
            'Service *' => 'Service *',
            '— Select a service —' => '— Sélectionnez un service —',
            'Package' => 'Forfait',
            '— No package / custom —' => '— Aucun forfait / personnalisé —',
            'Packages are loaded according to the selected service.' => 'Les forfaits sont chargés selon le service sélectionné.',
            'Date & time' => 'Date et heure',
            'Preferred date *' => 'Date souhaitée *',
            'Number of people *' => 'Nombre de personnes *',
            'Available start times *' => 'Heures de début disponibles *',
            'Select a date to see available times.' => 'Sélectionnez une date pour voir les heures disponibles.',
            'End time *' => 'Heure de fin *',
            'Event type' => 'Type d’événement',
            'Wedding bookings can contain more than one event day. Add every date included in the wedding package below.' => 'Les réservations de mariage peuvent comporter plusieurs jours d’événement. Ajoutez ci-dessous chaque date incluse dans le forfait mariage.',
            'Wedding Day' => 'Jour du mariage',
            'Remove' => 'Supprimer',
            'Date *' => 'Date *',
            'Event / Day Type *' => 'Type d’événement / journée *',
            'Start time *' => 'Heure de début *',
            'End time *' => 'Heure de fin *',
            '+ Add another wedding date' => '+ Ajouter une autre date de mariage',
            'Your details' => 'Vos informations',
            'Full name *' => 'Nom complet *',
            'Phone number *' => 'Numéro de téléphone *',
            'WhatsApp' => 'WhatsApp',
            'Your location *' => 'Votre localisation *',
            'Company' => 'Entreprise',
            'Event location' => 'Lieu de l’événement',
            'Additional notes' => 'Notes supplémentaires',
            'Payment' => 'Paiement',
            '30% deposit required.' => 'Un acompte de 30 % est requis.',
            'A minimum deposit of 30% of the booking total is required when submitting your booking request.' => 'Un acompte minimum de 30 % du total de la réservation est requis lors de l’envoi de votre demande.',
            'Payment method *' => 'Mode de paiement *',
            '— Select payment method —' => '— Sélectionnez le mode de paiement —',
            'Cash' => 'Espèces',
            'Mobile Money' => 'Mobile Money',
            'Bank Transfer' => 'Virement bancaire',
            'Other' => 'Autre',
            'Amount paid (RWF) *' => 'Montant payé (RWF) *',
            'Select a service to calculate the required 30% deposit.' => 'Sélectionnez un service pour calculer l’acompte de 30 % requis.',
            'Total' => 'Total',
            'Required deposit (30%)' => 'Acompte requis (30 %)',
            'Amount paid' => 'Montant payé',
            'Remaining balance' => 'Solde restant',
            'Submit Booking Request' => 'Envoyer la demande de réservation',
            'Your booking will first be submitted as' => 'Votre réservation sera d’abord soumise comme',
            'Pending' => 'En attente',
            'Our team will review it and confirm it. Once confirmed, your invoice will become available.' => 'Notre équipe l’examinera et la confirmera. Une fois confirmée, votre facture sera disponible.',
            'Booking Confirmation' => 'Confirmation de réservation',
            'Your booking number is' => 'Votre numéro de réservation est',
            'Confirmation' => 'Confirmation',
            'Your booking is approved' => 'Votre réservation est approuvée',
            'Your booking has been confirmed by Imagination Nature Studio. We look forward to capturing your moments.' => 'Votre réservation a été confirmée par Imagination Nature Studio. Nous avons hâte d’immortaliser vos moments.',
            'This booking was cancelled' => 'Cette réservation a été annulée',
            'Contact us if this was unexpected.' => 'Contactez-nous si cela était inattendu.',
            'Awaiting confirmation' => 'En attente de confirmation',
            'Your booking request has been received. Our team will review it and confirm the appointment.' => 'Votre demande de réservation a été reçue. Notre équipe l’examinera et confirmera le rendez-vous.',
            'Your invoice will become available after your booking is approved.' => 'Votre facture sera disponible après approbation de votre réservation.',
            'Checking booking status automatically…' => 'Vérification automatique du statut de la réservation…',
            'Your Invoice' => 'Votre facture',
            'Your booking has been approved. Your invoice is now available.' => 'Votre réservation a été approuvée. Votre facture est maintenant disponible.',
            'Download Invoice' => 'Télécharger la facture',
            'Amount due & payment instructions' => 'Montant dû et instructions de paiement',
            'A receipt will be issued by our team once your payment is confirmed.' => 'Un reçu sera délivré par notre équipe une fois votre paiement confirmé.',
            'Your invoice is being prepared.' => 'Votre facture est en préparation.',
            'Pay with MoMo' => 'Payer avec MoMo',
            'Scan the QR code below to make your payment.' => 'Scannez le QR code ci-dessous pour effectuer votre paiement.',
            'After payment, send proof to WhatsApp' => 'Après le paiement, envoyez la preuve sur WhatsApp',
            'with your booking number' => 'avec votre numéro de réservation',
            'A confirmation email has been sent to' => 'Un e-mail de confirmation a été envoyé à',
            'Send via WhatsApp' => 'Envoyer via WhatsApp',
            'Back to home' => 'Retour à l’accueil',
            'Invalid Promotion' => 'Promotion invalide',
            'Promotion not found' => 'Promotion introuvable',
            'The promotion link is invalid.' => 'Le lien de promotion est invalide.',
            'Go to Website' => 'Aller au site',
            'Promotion Not Found' => 'Promotion introuvable',
            'This promotion may have been removed or is no longer available.' => 'Cette promotion a peut-être été supprimée ou n’est plus disponible.',
            'WhatsApp Unavailable' => 'WhatsApp indisponible',
            'This promotion does not have a WhatsApp number configured yet.' => 'Aucun numéro WhatsApp n’est encore configuré pour cette promotion.',
            'Back to Website' => 'Retour au site',
        ],
    ];

    foreach (($extra[$lang] ?? []) as $en => $translated) {
        $cache[$lang][$en] = $translated;
    }

    return $cache[$lang];
}

function ims_translate_output(string $buffer): string
{
    if ($buffer === '' || site_lang() === 'en') {
        return $buffer;
    }

    $lang = site_lang();
    $map = ims_phrase_translations($lang);
    if (!$map) return $buffer;

    /*
     * Sort longest phrases first. This prevents a short phrase such as
     * "Book" from being translated before a longer phrase such as
     * "Book Your Session".
     */
    uksort($map, static function ($a, $b) {
        return mb_strlen((string)$b) <=> mb_strlen((string)$a);
    });

    /* Protect code blocks. We must NEVER translate CSS, JS, JSON, SVG code,
       URLs or PHP-generated data inside script/style/pre/code/textarea blocks. */
    $protected = [];
    $protect = static function ($m) use (&$protected) {
        $key = "__IMS_LANG_PROTECTED_" . count($protected) . "__";
        $protected[$key] = $m[0];
        return $key;
    };

    $buffer = preg_replace_callback(
        '~<(script|style|pre|code|textarea)\b[^>]*>.*?</\1>~is',
        $protect,
        $buffer
    );

    /* Protect PHP/HTML comments. */
    $buffer = preg_replace_callback('~<!--.*?-->~s', $protect, $buffer);

    /*
     * Translate visible text nodes only. HTML tags are kept untouched.
     * We also preserve whitespace exactly as it was.
     */
    $buffer = preg_replace_callback(
        '~([^<]+)(?=<|$)~s',
        static function ($m) use ($map) {
            $text = $m[1];

            foreach ($map as $english => $translated) {
                if ($english === '' || $english === $translated) continue;
                $text = str_replace($english, $translated, $text);
            }

            return $text;
        },
        $buffer
    );

    /* Translate common visible HTML attributes as well. */
    $attributeNames = [
        'placeholder', 'title', 'aria-label', 'aria-description',
        'data-label', 'data-title', 'alt'
    ];

    foreach ($attributeNames as $attribute) {
        $pattern = '~(\b' . preg_quote($attribute, '~') . '\s*=\s*["\'])(.*?)(["\'])~is';
        $buffer = preg_replace_callback(
            $pattern,
            static function ($m) use ($map) {
                $value = $m[2];
                foreach ($map as $english => $translated) {
                    if ($english === '' || $english === $translated) continue;
                    $value = str_replace($english, $translated, $value);
                }
                return $m[1] . $value . $m[3];
            },
            $buffer
        );
    }

    if ($protected) {
        $buffer = strtr($buffer, $protected);
    }

    return $buffer;
}


?>
