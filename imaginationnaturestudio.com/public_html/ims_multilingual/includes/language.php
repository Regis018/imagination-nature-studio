<?php
/**
 * Imagination Nature Studio — Public website language engine
 * Languages: English (en), Kinyarwanda (rw), Français (fr)
 */

if (!function_exists('ims_supported_languages')) {
    function ims_supported_languages(): array
    {
        return [
            'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇬🇧', 'html' => 'en'],
            'rw' => ['name' => 'Kinyarwanda', 'native' => 'Kinyarwanda', 'flag' => '🇷🇼', 'html' => 'rw-RW'],
            'fr' => ['name' => 'French', 'native' => 'Français', 'flag' => '🇫🇷', 'html' => 'fr-FR'],
        ];
    }

    function ims_detect_language(): string
    {
        $supported = ims_supported_languages();

        if (isset($_GET['lang']) && isset($supported[$_GET['lang']])) {
            $lang = $_GET['lang'];
            $_SESSION['site_language'] = $lang;
            setcookie('ins_language', $lang, [
                'expires' => time() + (86400 * 365),
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            return $lang;
        }

        if (!empty($_SESSION['site_language']) && isset($supported[$_SESSION['site_language']])) {
            return $_SESSION['site_language'];
        }

        if (!empty($_COOKIE['ins_language']) && isset($supported[$_COOKIE['ins_language']])) {
            $_SESSION['site_language'] = $_COOKIE['ins_language'];
            return $_COOKIE['ins_language'];
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if (str_starts_with($accept, 'fr')) {
            return 'fr';
        }
        if (str_starts_with($accept, 'rw') || str_starts_with($accept, 'kin')) {
            return 'rw';
        }

        return 'en';
    }

    function current_language(): string
    {
        static $lang = null;
        if ($lang !== null) return $lang;
        $lang = ims_detect_language();
        return $lang;
    }

    function language_info(?string $lang = null): array
    {
        $lang = $lang ?: current_language();
        $all = ims_supported_languages();
        return $all[$lang] ?? $all['en'];
    }

    function language_url(string $lang): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $query = $_GET;
        $query['lang'] = $lang;
        return $path . '?' . http_build_query($query);
    }

    function ims_dictionary(): array
    {
        return [
            'Home' => ['rw' => 'Ahabanza', 'fr' => 'Accueil'],
            'About' => ['rw' => 'Abo Turi', 'fr' => 'À propos'],
            'About us' => ['rw' => 'Abo Turi', 'fr' => 'À propos de nous'],
            'Services' => ['rw' => 'Serivisi', 'fr' => 'Services'],
            'Portfolio' => ['rw' => 'Portfolio', 'fr' => 'Portfolio'],
            'Contact' => ['rw' => 'Twandikire', 'fr' => 'Contact'],
            'Book a Session' => ['rw' => 'Andika gahunda', 'fr' => 'Réserver une séance'],
            'Book Your Session' => ['rw' => 'Andika gahunda yawe', 'fr' => 'Réservez votre séance'],
            'Book This Service' => ['rw' => 'Andika iyi serivisi', 'fr' => 'Réserver ce service'],
            'View all services' => ['rw' => 'Reba serivisi zose', 'fr' => 'Voir tous les services'],
            'From' => ['rw' => 'Guhera kuri', 'fr' => 'À partir de'],
            'On request' => ['rw' => 'Ku busabe', 'fr' => 'Sur demande'],
            'No services available yet.' => ['rw' => 'Nta serivisi ziraboneka.', 'fr' => 'Aucun service disponible pour le moment.'],
            'Everything we offer.' => ['rw' => 'Serivisi zacu zose.', 'fr' => 'Tout ce que nous proposons.'],
            'Photography with intent, in the heart of Kigali.' => ['rw' => 'Amafoto akorwa n’umwete, mu mutima wa Kigali.', 'fr' => 'La photographie avec intention, au cœur de Kigali.'],
            'Photography & Creative Studio' => ['rw' => 'STUDIO Y’AMAFOTO N’UBUHANGA', 'fr' => 'STUDIO PHOTO & CRÉATIF'],
            'PHOTOGRAPHY &amp; CREATIVE STUDIO' => ['rw' => 'STUDIO Y’AMAFOTO N’UBUHANGA', 'fr' => 'STUDIO PHOTO & CRÉATIF'],
            'Selected stories.' => ['rw' => 'Inkuru twahisemo.', 'fr' => 'Des histoires sélectionnées.'],
            'Why Choose Us' => ['rw' => 'Kuki Mutuhitamo?', 'fr' => 'Pourquoi nous choisir ?'],
            'More Than Photography.' => ['rw' => 'Turarenza gufata amafoto.', 'fr' => 'Bien plus que de la photographie.'],
            'We Create Memories.' => ['rw' => 'Duhanga urwibutso.', 'fr' => 'Nous créons des souvenirs.'],
            'More Than Photography. We Create Memories.' => ['rw' => 'Turarenza gufata amafoto. Duhanga urwibutso.', 'fr' => 'Bien plus que de la photographie. Nous créons des souvenirs.'],
            'Professional Photography' => ['rw' => 'Amafoto y’umwuga', 'fr' => 'Photographie professionnelle'],
            'Photo & Video' => ['rw' => 'Amafoto na Video', 'fr' => 'Photo & Vidéo'],
            'Premium Printing & Frames' => ['rw' => 'Gucapa no gukora amaframe meza', 'fr' => 'Impression premium & cadres'],
            'Fast Delivery' => ['rw' => 'Gutanga vuba', 'fr' => 'Livraison rapide'],
            'Professional Retouching' => ['rw' => 'Gutunganya amafoto mu buryo bw’umwuga', 'fr' => 'Retouche professionnelle'],
            'Personalized Service' => ['rw' => 'Serivisi ijyanye nawe', 'fr' => 'Service personnalisé'],
            'Explore Our Services' => ['rw' => 'Reba serivisi zacu', 'fr' => 'Découvrez nos services'],
            'Client Reviews' => ['rw' => 'Ibitekerezo by’abakiliya', 'fr' => 'Avis des clients'],
            'What Our Clients Say' => ['rw' => 'Ibyo abakiliya bacu bavuga', 'fr' => 'Ce que disent nos clients'],
            'Google Review' => ['rw' => 'Isuzuma kuri Google', 'fr' => 'Avis Google'],
            'Leave a Google Review' => ['rw' => 'Siga igitekerezo kuri Google', 'fr' => 'Laisser un avis Google'],
            'Share your experience with us' => ['rw' => 'Sangiza abandi ubunararibonye bwawe', 'fr' => 'Partagez votre expérience avec nous'],
            "Have you worked with us? We'd love to hear about your experience on Google." => ['rw' => 'Wigeze gukorana natwe? Twishimira kumva uko watubonye kuri Google.', 'fr' => 'Vous avez travaillé avec nous ? Nous aimerions connaître votre expérience sur Google.'],
            'Visit Our Studio' => ['rw' => 'Sura studio yacu', 'fr' => 'Visitez notre studio'],
            'Get Directions' => ['rw' => 'Reba inzira', 'fr' => 'Itinéraire'],
            'Find Imagination Nature Studio' => ['rw' => 'Shaka Imagination Nature Studio', 'fr' => 'Trouver Imagination Nature Studio'],
            'Let\'s talk.' => ['rw' => 'Reka tuganire.', 'fr' => 'Parlons-en.'],
            'Contact us' => ['rw' => 'Twandikire', 'fr' => 'Contactez-nous'],
            'Explore' => ['rw' => 'Reba byinshi', 'fr' => 'Explorer'],
            'Follow' => ['rw' => 'Dukurikire', 'fr' => 'Suivez-nous'],
            'Social' => ['rw' => 'Imbuga nkoranyambaga', 'fr' => 'Réseaux sociaux'],
            'Instagram' => ['rw' => 'Instagram', 'fr' => 'Instagram'],
            'TikTok' => ['rw' => 'TikTok', 'fr' => 'TikTok'],
            'Facebook' => ['rw' => 'Facebook', 'fr' => 'Facebook'],
            'YouTube' => ['rw' => 'YouTube', 'fr' => 'YouTube'],
            'Weddings' => ['rw' => 'Ubukwe', 'fr' => 'Mariages'],
            'Wedding Photography' => ['rw' => 'Amafoto y’ubukwe', 'fr' => 'Photographie de mariage'],
            'Portraits' => ['rw' => 'Portraits', 'fr' => 'Portraits'],
            'Maternity' => ['rw' => 'Ababyeyi batwite', 'fr' => 'Maternité'],
            'Family' => ['rw' => 'Imiryango', 'fr' => 'Famille'],
            'Editorial' => ['rw' => 'Editorial', 'fr' => 'Éditorial'],
            'Events' => ['rw' => 'Ibirori', 'fr' => 'Événements'],
            'Commercial' => ['rw' => 'Ubucuruzi', 'fr' => 'Commercial'],
            'Kigali · Rwanda' => ['rw' => 'Kigali · Rwanda', 'fr' => 'Kigali · Rwanda'],
            'SPECIAL OFFER' => ['rw' => 'IGITANGAZA CYIHARIYE', 'fr' => 'OFFRE SPÉCIALE'],
            'SAVE' => ['rw' => 'BUNGUKA', 'fr' => 'ÉCONOMISEZ'],
            'Days' => ['rw' => 'Iminsi', 'fr' => 'Jours'],
            'Hours' => ['rw' => 'Amasaha', 'fr' => 'Heures'],
            'Min' => ['rw' => 'Min', 'fr' => 'Min'],
            'Sec' => ['rw' => 'Sec', 'fr' => 'Sec'],
            'Limited-time offer • Subject to availability' => ['rw' => 'Iyi promotion ifite igihe gito • Bitewe n’uko serivisi iboneka.', 'fr' => 'Offre limitée • Sous réserve de disponibilité'],
            'Tap to order' => ['rw' => 'Kanda usabe', 'fr' => 'Appuyez pour commander'],
            'Offer ending soon' => ['rw' => 'Promotion iri kurangira vuba', 'fr' => 'L’offre se termine bientôt'],
            'Booking received — thank you!' => ['rw' => 'Booking yakiriwe — murakoze!', 'fr' => 'Réservation reçue — merci !'],
            'New booking received' => ['rw' => 'Booking nshya yakiriwe', 'fr' => 'Nouvelle réservation reçue'],
            'Invalid Promotion' => ['rw' => 'Promotion itari yo', 'fr' => 'Promotion invalide'],
            'Promotion Not Found' => ['rw' => 'Promotion ntiriboneka', 'fr' => 'Promotion introuvable'],
            'WhatsApp Unavailable' => ['rw' => 'WhatsApp ntiboneka', 'fr' => 'WhatsApp indisponible'],
            'WhatsApp us' => ['rw' => 'Twandikire kuri WhatsApp', 'fr' => 'Écrivez-nous sur WhatsApp'],
            'Send message' => ['rw' => 'Ohereza ubutumwa', 'fr' => 'Envoyer le message'],
            'Thank you — your message has been received.' => ['rw' => 'Murakoze — ubutumwa bwanyu bwakiriwe.', 'fr' => 'Merci — votre message a été reçu.'],
            'Name *' => ['rw' => 'Amazina *', 'fr' => 'Nom *'],
            'Email *' => ['rw' => 'Email *', 'fr' => 'E-mail *'],
            'Phone' => ['rw' => 'Telefoni', 'fr' => 'Téléphone'],
            'Email' => ['rw' => 'Email', 'fr' => 'E-mail'],
            'Message *' => ['rw' => 'Ubutumwa *', 'fr' => 'Message *'],
            'Subject' => ['rw' => 'Umutwe', 'fr' => 'Objet'],
            'Date' => ['rw' => 'Itariki', 'fr' => 'Date'],
            'Time' => ['rw' => 'Igihe', 'fr' => 'Heure'],
            'Location' => ['rw' => 'Aho bizabera', 'fr' => 'Lieu'],
            'Service' => ['rw' => 'Serivisi', 'fr' => 'Service'],
            'Package' => ['rw' => 'Package', 'fr' => 'Forfait'],
            'Client' => ['rw' => 'Umukiliya', 'fr' => 'Client'],
            'Booking No' => ['rw' => 'Nimero ya booking', 'fr' => 'N° de réservation'],
            'Booking number' => ['rw' => 'Nimero ya booking', 'fr' => 'Numéro de réservation'],
            'Amount paid' => ['rw' => 'Amafaranga yishyuwe', 'fr' => 'Montant payé'],
            'Deposit' => ['rw' => 'Avansi', 'fr' => 'Acompte'],
            'Deposit (30%)' => ['rw' => 'Avansi (30%)', 'fr' => 'Acompte (30 %)'],
            'Balance' => ['rw' => 'Asigaye', 'fr' => 'Solde'],
            'Total' => ['rw' => 'Igiteranyo', 'fr' => 'Total'],
            'Pending' => ['rw' => 'Birategerejwe', 'fr' => 'En attente'],
            'Duration' => ['rw' => 'Igihe bimara', 'fr' => 'Durée'],
            '30% deposit required.' => ['rw' => 'Harasabwa avansi ya 30%.', 'fr' => 'Un acompte de 30 % est requis.'],
            'Studio' => ['rw' => 'Studio', 'fr' => 'Studio'],
            'PHOTOGRAPHY & CREATIVE STUDIO' => ['rw' => 'STUDIO Y’AMAFOTO N’UBUHANGA', 'fr' => 'STUDIO PHOTO & CRÉATIF'],
            'Kigali, Rwanda' => ['rw' => 'Kigali, Rwanda', 'fr' => 'Kigali, Rwanda'],
            'weekly' => ['rw' => 'buri cyumweru', 'fr' => 'hebdomadaire'],
            'Loading…' => ['rw' => 'Birimo gutegurwa…', 'fr' => 'Chargement…'],
            'Share' => ['rw' => 'Sangiza', 'fr' => 'Partager'],
            'All' => ['rw' => 'Byose', 'fr' => 'Tout'],
            'Selected stories.' => ['rw' => 'Inkuru twahisemo.', 'fr' => 'Des histoires sélectionnées.'],
            'A0' => ['rw' => 'A0', 'fr' => 'A0'],
            'Studio · Kigali' => ['rw' => 'Studio · Kigali', 'fr' => 'Studio · Kigali'],
            'Photography Studio in Kigali' => ['rw' => 'Studio y’amafoto i Kigali', 'fr' => 'Studio photo à Kigali'],
            'Studio room photoshoot' => ['rw' => 'Gufotora muri studio', 'fr' => 'Séance photo en studio'],
            'Outdoor photoshoot' => ['rw' => 'Gufotora hanze', 'fr' => 'Séance photo en extérieur'],
            'Wedding coverage' => ['rw' => 'Gufotora ubukwe', 'fr' => 'Couverture de mariage'],
            'Bridal shower' => ['rw' => 'Bridal shower', 'fr' => 'Bridal shower'],
            'Anniversary' => ['rw' => 'Isabukuru y’ubukwe', 'fr' => 'Anniversaire de mariage'],
            'Frames' => ['rw' => 'Amaframe', 'fr' => 'Cadres'],
            'Printing' => ['rw' => 'Gucapa', 'fr' => 'Impression'],
            'Photobooks' => ['rw' => 'Photobooks', 'fr' => 'Livres photo'],
            'Portrait photography' => ['rw' => 'Amafoto ya portrait', 'fr' => 'Photographie de portrait'],
            'Event coverage' => ['rw' => 'Gufotora ibirori', 'fr' => 'Couverture d’événements'],
            'Fashion shoots' => ['rw' => 'Amafoto y’imyambaro', 'fr' => 'Séances mode'],
            'Photo printing' => ['rw' => 'Gucapa amafoto', 'fr' => 'Impression photo'],
            'Photo books' => ['rw' => 'Photobooks', 'fr' => 'Livres photo'],
            'Printing & Frames' => ['rw' => 'Gucapa na Amaframe', 'fr' => 'Impression & Cadres'],
            'Creative Studio' => ['rw' => 'Studio y’ubuhanzi', 'fr' => 'Studio créatif'],
        ];
    }

    function t(string $key, ?string $fallback = null, ?string $lang = null): string
    {
        $lang = $lang ?: current_language();
        if ($lang === 'en') return $fallback ?? $key;
        $dict = ims_dictionary();
        if (isset($dict[$key][$lang])) return $dict[$key][$lang];
        return $fallback ?? $key;
    }

    function translate_text(string $text, ?string $lang = null): string
    {
        $lang = $lang ?: current_language();
        if ($lang === 'en' || trim($text) === '') return $text;

        $dict = ims_dictionary();
        $trimmed = trim($text);
        if (isset($dict[$trimmed][$lang])) {
            $replacement = $dict[$trimmed][$lang];
            $prefix = substr($text, 0, strlen($text) - strlen(ltrim($text)));
            $suffix = substr($text, strlen(rtrim($text)));
            return $prefix . $replacement . $suffix;
        }

        return $text;
    }

    function ims_translate_output(string $html): string
    {
        if (current_language() === 'en' || $html === '') return $html;

        // Translate visible text nodes without touching PHP/JS because this runs after rendering.
        $html = preg_replace_callback('/>([^<>]+)</u', function ($m) {
            return '>' . translate_text($m[1]) . '<';
        }, $html);

        // Translate accessibility/UI attributes only. Do not touch data-* or value attributes.
        $html = preg_replace_callback('/\b(aria-label|title|placeholder|alt)=(["\'])(.*?)\2/isu', function ($m) {
            return $m[1] . '=' . $m[2] . translate_text($m[3]) . $m[2];
        }, $html);

        return $html;
    }

    function language_label(string $lang): string
    {
        $info = language_info($lang);
        return $info['flag'] . ' ' . $info['native'];
    }
}
