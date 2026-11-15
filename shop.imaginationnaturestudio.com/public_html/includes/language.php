```php
<?php
/**
 * Frame Zone Kigali — Language System
 * Languages: English, Kinyarwanda, Kiswahili, French
 */

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

$supported_languages = [
    'en' => 'English 🇬🇧',
    'rw' => 'Kinyarwanda 🇷🇼',
    'sw' => 'Kiswahili 🇰🇪',
    'fr' => 'Français 🇫🇷',
];

$selected_language = 'en';

if (
    isset($_GET['lang']) &&
    is_string($_GET['lang']) &&
    isset($supported_languages[$_GET['lang']])
) {
    $selected_language = $_GET['lang'];

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['frame_zone_lang'] = $selected_language;
    }

    if (!headers_sent()) {
        setcookie('frame_zone_lang', $selected_language, [
            'expires'  => time() + (365 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) &&
                          $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
} elseif (
    isset($_SESSION['frame_zone_lang']) &&
    is_string($_SESSION['frame_zone_lang']) &&
    isset($supported_languages[$_SESSION['frame_zone_lang']])
) {
    $selected_language = $_SESSION['frame_zone_lang'];
} elseif (
    isset($_COOKIE['frame_zone_lang']) &&
    is_string($_COOKIE['frame_zone_lang']) &&
    isset($supported_languages[$_COOKIE['frame_zone_lang']])
) {
    $selected_language = $_COOKIE['frame_zone_lang'];
}

if (!function_exists('fz_lang')) {
    function fz_lang(): string
    {
        global $selected_language;
        return $selected_language ?? 'en';
    }
}

if (!function_exists('fz_languages')) {
    function fz_languages(): array
    {
        global $supported_languages;
        return $supported_languages;
    }
}

if (!function_exists('fz_t')) {
    function fz_t(string $key): string
    {
        static $translations = [

            'en' => [
                'home' => 'Home',
                'football_clubs' => 'Football Clubs',
                'legends' => 'Football Legends',
                'celebrities' => 'Celebrities',
                'wedding_frames' => 'Wedding Frames',
                'custom_frames' => 'Custom Frames',
                'about' => 'About Us',
                'contact' => 'Contact',
                'search' => 'Search frames...',
                'cart' => 'Cart',
                'explore_football' => 'Explore Football Frames',
                'discover_celebrity' => 'Discover Celebrity Frames',
                'order_whatsapp' => 'Order on WhatsApp',
                'featured_frames' => 'Featured Frames',
                'view_all' => 'View All',
                'add_to_cart' => 'Add to Cart',
                'order_now' => 'Order Now',
                'price' => 'Price',
                'size' => 'Size',
                'description' => 'Description',
                'related_frames' => 'Related Frames',
                'empty_cart' => 'Your cart is empty.',
                'continue_shopping' => 'Continue Shopping',
                'checkout' => 'Checkout',
                'name' => 'Full Name',
                'phone' => 'Phone Number',
                'email' => 'Email Address',
                'address' => 'Delivery Address',
                'place_order' => 'Place Order',
                'why_shop' => 'Why Shop With Us',
                'premium_quality' => 'Premium Quality',
                'fast_delivery' => 'Fast Delivery',
                'easy_ordering' => 'Easy Ordering',
                'custom_design' => 'Custom Design',
                'how_to_order' => 'How to Order',
                'ready_to_order' => 'Ready to Order?',
                'eyebrow' => 'FRAME ZONE KIGALI · RWANDA',
                'popular_categories' => 'Popular Categories',
                'english_premier_league' => 'English Premier League',
                'la_liga' => 'La Liga',
                'see_all' => 'See All',
                'serie_a' => 'Serie A',
                'bundesliga' => 'Bundesliga',
                'ligue_1' => 'Ligue 1',
                'african_international' => 'African & International',
                'custom_cta_title' => 'Want Something Personal?',
                'custom_cta_text' => 'Choose a club, player or family photo, and we will create a custom frame just for you.',
                'request_custom_frame' => 'Request a Custom Frame',
                'premium_quality_text' => 'Frames built to last, crafted with care.',
                'fast_delivery_text' => 'Delivery across Kigali and Rwanda.',
                'easy_ordering_text' => 'Order directly via WhatsApp in seconds.',
                'custom_design_text' => 'Personalised frames for any space or occasion.',
                'step_browse' => 'Browse our football and celebrity frames.',
                'step_select' => 'Choose your preferred size and add it to your cart.',
                'step_send' => 'Send your order via WhatsApp for confirmation.',
                'step_receive' => 'Receive your frame at your door.',
                'faq_title' => 'Frequently Asked Questions',
                'faq_delivery_q' => 'Do you deliver outside Kigali?',
                'faq_delivery_a' => 'Delivery charges are confirmed via WhatsApp before payment.',
                'faq_custom_q' => 'Can I order a custom frame?',
                'faq_custom_a' => 'Share your requirements on our Custom Frames page or WhatsApp.',
                'faq_sizes_q' => 'What sizes are available?',
                'faq_sizes_a' => 'Available sizes are listed on each product page. Custom sizes can be requested.',
                'faq_payment_q' => 'How do I pay?',
                'faq_payment_a' => 'Payment instructions are provided after your order is confirmed on WhatsApp.',
                'chat_whatsapp' => 'Chat with us on WhatsApp:',
            ],

            'rw' => [
                'home' => 'Ahabanza',
                'football_clubs' => 'Amakipe y’Umupira',
                'legends' => 'Abakinnyi b’Ibyamamare',
                'celebrities' => 'Ibyamamare',
                'wedding_frames' => 'Ama-frame y’Ubukwe',
                'custom_frames' => 'Ama-frame Yihariye',
                'about' => 'Abo Turi Bo',
                'contact' => 'Twandikire',
                'search' => 'Shakisha ama-frame...',
                'cart' => 'Ibyo Wahisemo',
                'explore_football' => 'Reba Ama-frame y’Amakipe',
                'discover_celebrity' => 'Reba Ama-frame y’Ibyamamare',
                'order_whatsapp' => 'Tumiza kuri WhatsApp',
                'featured_frames' => 'Ama-frame Yatoranyijwe',
                'view_all' => 'Reba Byose',
                'add_to_cart' => 'Shyira mu Ibyo Wahisemo',
                'order_now' => 'Tumiza Ubu',
                'price' => 'Igiciro',
                'size' => 'Ingano',
                'description' => 'Ibisobanuro',
                'related_frames' => 'Ama-frame Bisa',
                'empty_cart' => 'Nta bicuruzwa wahisemo.',
                'continue_shopping' => 'Komeza Guhaha',
                'checkout' => 'Komeza Utumize',
                'name' => 'Amazina Yuzuye',
                'phone' => 'Nimero ya Telefoni',
                'email' => 'Imeyili',
                'address' => 'Aho Uzagemurirwa',
                'place_order' => 'Ohereza Commande',
                'why_shop' => 'Impamvu Waduhitamo',
                'premium_quality' => 'Ubwiza Bwizewe',
                'fast_delivery' => 'Kugemura Vuba',
                'easy_ordering' => 'Gutumiza mu buryo Bworoshye',
                'custom_design' => 'Design Yihariye',
                'how_to_order' => 'Uko Watumiza',
                'ready_to_order' => 'Witeguye Gutumiza?',
                'eyebrow' => 'FRAME ZONE KIGALI · RWANDA',
                'popular_categories' => 'Ibyiciro Bikunzwe',
                'english_premier_league' => 'Shampiyona y’u Bwongereza',
                'la_liga' => 'Shampiyona ya La Liga',
                'see_all' => 'Reba Byose',
                'serie_a' => 'Serie A',
                'bundesliga' => 'Bundesliga',
                'ligue_1' => 'Ligue 1',
                'african_international' => 'Afurika n’Amakipe Mpuzamahanga',
                'custom_cta_title' => 'Urashaka frame yihariye?',
                'custom_cta_text' => 'Hitamo ikipe, umukinnyi cyangwa ifoto y’umuryango, tugukorere frame yihariye.',
                'request_custom_frame' => 'Saba Frame Yihariye',
                'premium_quality_text' => 'Ama-frame meza kandi akorwa mu buryo burambye.',
                'fast_delivery_text' => 'Tugeza ibicuruzwa i Kigali no mu Rwanda hose.',
                'easy_ordering_text' => 'Tegeka byoroshye unyuze kuri WhatsApp.',
                'custom_design_text' => 'Ama-frame yihariye ajyanye n’ahantu cyangwa ibirori.',
                'step_browse' => 'Reba ama-frame y’amakipe, abakinnyi n’ibyamamare.',
                'step_select' => 'Hitamo ingano ushaka uyishyire mu byo wahisemo.',
                'step_send' => 'Ohereza commande kuri WhatsApp kugira ngo yemezwe.',
                'step_receive' => 'Akira frame yawe aho utuye.',
                'faq_title' => 'Ibibazo Bikunze Kubazwa',
                'faq_delivery_q' => 'Mugeza ibicuruzwa hanze ya Kigali?',
                'faq_delivery_a' => 'Amafaranga yo kugeza ibicuruzwa aho uri yemezwa kuri WhatsApp mbere yo kwishyura.',
                'faq_custom_q' => 'Nshobora gutumiza frame yihariye?',
                'faq_custom_a' => 'Tanga ibisobanuro kuri paji ya Custom Frames cyangwa kuri WhatsApp.',
                'faq_sizes_q' => 'Ni izihe ngano mufite?',
                'faq_sizes_a' => 'Ingano zihari zigaragara kuri buri gicuruzwa. Ushobora no gusaba ingano yihariye.',
                'faq_payment_q' => 'Nishyura nte?',
                'faq_payment_a' => 'Uhabwa amabwiriza yo kwishyura nyuma yo kwemeza commande kuri WhatsApp.',
                'chat_whatsapp' => 'Twandikire kuri WhatsApp:',
            ],

            'sw' => [
                'home' => 'Mwanzo',
                'football_clubs' => 'Vilabu vya Soka',
                'legends' => 'Magwiji wa Soka',
                'celebrities' => 'Watu Mashuhuri',
                'wedding_frames' => 'Fremu za Harusi',
                'custom_frames' => 'Fremu Maalum',
                'about' => 'Kuhusu Sisi',
                'contact' => 'Wasiliana Nasi',
                'search' => 'Tafuta fremu...',
                'cart' => 'Kikapu',
                'explore_football' => 'Tazama Fremu za Soka',
                'discover_celebrity' => 'Tazama Fremu za Watu Mashuhuri',
                'order_whatsapp' => 'Agiza kupitia WhatsApp',
                'featured_frames' => 'Fremu Zilizochaguliwa',
                'view_all' => 'Tazama Zote',
                'add_to_cart' => 'Ongeza kwenye Kikapu',
                'order_now' => 'Agiza Sasa',
                'price' => 'Bei',
                'size' => 'Ukubwa',
                'description' => 'Maelezo',
                'related_frames' => 'Fremu Zinazofanana',
                'empty_cart' => 'Kikapu chako kiko tupu.',
                'continue_shopping' => 'Endelea Kununua',
                'checkout' => 'Endelea na Malipo',
                'name' => 'Jina Kamili',
                'phone' => 'Nambari ya Simu',
                'email' => 'Barua Pepe',
                'address' => 'Anwani ya Uwasilishaji',
                'place_order' => 'Tuma Oda',
                'why_shop' => 'Kwa Nini Utuchague',
                'premium_quality' => 'Ubora wa Juu',
                'fast_delivery' => 'Uwasilishaji wa Haraka',
                'easy_ordering' => 'Kuagiza kwa Urahisi',
                'custom_design' => 'Muundo Maalum',
                'how_to_order' => 'Jinsi ya Kuagiza',
                'ready_to_order' => 'Uko Tayari Kuagiza?',
                'eyebrow' => 'FRAME ZONE KIGALI · RWANDA',
                'popular_categories' => 'Makundi Maarufu',
                'english_premier_league' => 'Ligi Kuu ya Uingereza',
                'la_liga' => 'Ligi ya La Liga',
                'see_all' => 'Tazama Zote',
                'serie_a' => 'Serie A',
                'bundesliga' => 'Bundesliga',
                'ligue_1' => 'Ligue 1',
                'african_international' => 'Afrika na Kimataifa',
                'custom_cta_title' => 'Unataka fremu ya kipekee?',
                'custom_cta_text' => 'Chagua klabu, mchezaji au picha ya familia, nasi tutakutengenezea fremu maalum.',
                'request_custom_frame' => 'Omba Fremu Maalum',
                'premium_quality_text' => 'Fremu bora zinazotengenezwa kwa uangalifu.',
                'fast_delivery_text' => 'Tunawasilisha Kigali na kote Rwanda.',
                'easy_ordering_text' => 'Agiza kwa urahisi kupitia WhatsApp.',
                'custom_design_text' => 'Fremu maalum kwa nafasi au tukio lolote.',
                'step_browse' => 'Tazama fremu za klabu, wachezaji na watu maarufu.',
                'step_select' => 'Chagua ukubwa unaotaka na uweke kwenye kikapu.',
                'step_send' => 'Tuma oda kupitia WhatsApp ili ithibitishwe.',
                'step_receive' => 'Pokea fremu yako nyumbani.',
                'faq_title' => 'Maswali Yanayoulizwa Mara kwa Mara',
                'faq_delivery_q' => 'Je, mnasafirisha nje ya Kigali?',
                'faq_delivery_a' => 'Gharama za usafirishaji huthibitishwa kupitia WhatsApp kabla ya malipo.',
                'faq_custom_q' => 'Je, ninaweza kuagiza fremu maalum?',
                'faq_custom_a' => 'Eleza unachotaka kwenye ukurasa wa Custom Frames au WhatsApp.',
                'faq_sizes_q' => 'Ni ukubwa gani unaopatikana?',
                'faq_sizes_a' => 'Ukubwa unaopatikana umeorodheshwa kwenye ukurasa wa bidhaa. Unaweza kuomba ukubwa maalum.',
                'faq_payment_q' => 'Ninalipaje?',
                'faq_payment_a' => 'Maelekezo ya malipo hutolewa baada ya oda kuthibitishwa kupitia WhatsApp.',
                'chat_whatsapp' => 'Wasiliana nasi kupitia WhatsApp:',
            ],

            'fr' => [
                'home' => 'Accueil',
                'football_clubs' => 'Clubs de Football',
                'legends' => 'Légendes du Football',
                'celebrities' => 'Célébrités',
                'wedding_frames' => 'Cadres de Mariage',
                'custom_frames' => 'Cadres Personnalisés',
                'about' => 'À Propos',
                'contact' => 'Contact',
                'search' => 'Rechercher des cadres...',
                'cart' => 'Panier',
                'explore_football' => 'Voir les Cadres de Football',
                'discover_celebrity' => 'Voir les Cadres de Célébrités',
                'order_whatsapp' => 'Commander sur WhatsApp',
                'featured_frames' => 'Cadres à la Une',
                'view_all' => 'Tout Voir',
                'add_to_cart' => 'Ajouter au Panier',
                'order_now' => 'Commander',
                'price' => 'Prix',
                'size' => 'Taille',
                'description' => 'Description',
                'related_frames' => 'Cadres Similaires',
                'empty_cart' => 'Votre panier est vide.',
                'continue_shopping' => 'Continuer les Achats',
                'checkout' => 'Passer la Commande',
                'name' => 'Nom Complet',
                'phone' => 'Numéro de Téléphone',
                'email' => 'Adresse E-mail',
                'address' => 'Adresse de Livraison',
                'place_order' => 'Envoyer la Commande',
                'why_shop' => 'Pourquoi Nous Choisir',
                'premium_quality' => 'Qualité Premium',
                'fast_delivery' => 'Livraison Rapide',
                'easy_ordering' => 'Commande Facile',
                'custom_design' => 'Design Personnalisé',
                'how_to_order' => 'Comment Commander',
                'ready_to_order' => 'Prêt à Commander ?',
                'eyebrow' => 'FRAME ZONE KIGALI · RWANDA',
                'popular_categories' => 'Catégories Populaires',
                'english_premier_league' => 'Championnat Anglais',
                'la_liga' => 'La Liga',
                'see_all' => 'Voir Tout',
                'serie_a' => 'Serie A',
                'bundesliga' => 'Bundesliga',
                'ligue_1' => 'Ligue 1',
                'african_international' => 'Afrique et International',
                'custom_cta_title' => 'Vous souhaitez un cadre personnalisé ?',
                'custom_cta_text' => 'Choisissez un club, un joueur ou une photo de famille et nous créerons un cadre personnalisé.',
                'request_custom_frame' => 'Demander un Cadre Personnalisé',
                'premium_quality_text' => 'Des cadres de qualité, conçus pour durer.',
                'fast_delivery_text' => 'Livraison à Kigali et partout au Rwanda.',
                'easy_ordering_text' => 'Commandez facilement via WhatsApp.',
                'custom_design_text' => 'Des cadres personnalisés pour chaque espace ou occasion.',
                'step_browse' => 'Découvrez nos cadres de clubs, joueurs et célébrités.',
                'step_select' => 'Choisissez la taille souhaitée et ajoutez-la au panier.',
                'step_send' => 'Envoyez votre commande sur WhatsApp pour confirmation.',
                'step_receive' => 'Recevez votre cadre à domicile.',
                'faq_title' => 'Questions Fréquemment Posées',
                'faq_delivery_q' => 'Livrez-vous en dehors de Kigali ?',
                'faq_delivery_a' => 'Les frais de livraison sont confirmés sur WhatsApp avant le paiement.',
                'faq_custom_q' => 'Puis-je commander un cadre personnalisé ?',
                'faq_custom_a' => 'Indiquez vos souhaits sur notre page Custom Frames ou sur WhatsApp.',
                'faq_sizes_q' => 'Quelles tailles sont disponibles ?',
                'faq_sizes_a' => 'Les tailles disponibles figurent sur chaque produit. Des tailles personnalisées peuvent être demandées.',
                'faq_payment_q' => 'Comment puis-je payer ?',
                'faq_payment_a' => 'Les instructions de paiement sont communiquées après confirmation de la commande sur WhatsApp.',
                'chat_whatsapp' => 'Contactez-nous sur WhatsApp :',
            ],
        ];

        $language = fz_lang();

        return $translations[$language][$key]
            ?? $translations['en'][$key]
            ?? $key;
    }
}
```
