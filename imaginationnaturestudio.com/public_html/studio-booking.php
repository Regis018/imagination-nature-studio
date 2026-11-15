<?php
/**
 * ============================================================
 * IMAGINATION NATURE STUDIO
 * STUDIO BOOKING ENTRY POINT
 * ============================================================
 *
 * This file keeps the existing:
 *
 * https://imaginationnaturestudio.com/studio-booking.php
 *
 * URL working, but sends visitors to the NEW unified booking
 * form instead of the old Studio Photoshoot form.
 *
 * NEW BOOKING FORM:
 * /booking-simple.php
 *
 * Studio Photoshoot service ID:
 * 4
 *
 * IMPORTANT:
 * Do NOT put the old multi-step booking form here.
 * Do NOT modify booking/process.php from this file.
 * ============================================================
 */


/* ============================================================
   LOAD WEBSITE FUNCTIONS
   ============================================================ */

require_once __DIR__ . '/includes/functions.php';


/* ============================================================
   START / USE EXISTING SESSION
   ============================================================ */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}


/* ============================================================
   STUDIO PHOTOSHOOT SERVICE
   ============================================================
 *
 * Studio Photoshoot is currently service ID 4.
 *
 * We store this in booking_old so the NEW booking-simple.php
 * form can automatically select Studio Photoshoot.
 *
 * We do NOT create a new booking here.
 * ============================================================
 */

$_SESSION['booking_old'] = [
    'service_id' => '4'
];


/* ============================================================
   REMOVE OLD ERROR MESSAGES
   ============================================================
 *
 * If a visitor previously had an error on the old booking form,
 * we do not want that old error displayed on the new form.
 * ============================================================
 */

unset($_SESSION['booking_errors']);


/* ============================================================
   REDIRECT TO NEW BOOKING FORM
   ============================================================
 */

redirect(
    SITE_URL . '/booking-simple.php'
);

exit;