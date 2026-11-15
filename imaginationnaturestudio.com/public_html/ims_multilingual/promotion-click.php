<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROMOTION CLICK TRACKER
|--------------------------------------------------------------------------
| Handles:
|   - WhatsApp clicks
|   - Promotion click counting
|   - Safe WhatsApp redirect
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$pdo = db();


/*
|--------------------------------------------------------------------------
| GET PARAMETERS
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);

$type = strtolower(
    trim(
        (string)($_GET['type'] ?? '')
    )
);


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($id < 1) {

    http_response_code(400);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >
        <title>Invalid Promotion</title>

        <style>
            *{
                box-sizing:border-box;
            }

            body{
                margin:0;
                min-height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:20px;
                background:#111;
                color:#fff;
                font-family:Arial,Helvetica,sans-serif;
            }

            .box{
                width:100%;
                max-width:480px;
                padding:35px;
                text-align:center;
                background:#1b1b1b;
                border:1px solid rgba(255,255,255,.10);
                border-radius:18px;
            }

            h1{
                margin:0 0 10px;
                font-size:24px;
            }

            p{
                color:#aaa;
                line-height:1.6;
            }

            a{
                display:inline-block;
                margin-top:15px;
                padding:12px 18px;
                border-radius:10px;
                background:#fff;
                color:#111;
                text-decoration:none;
                font-weight:700;
            }
        </style>
    </head>

    <body>

        <div class="box">

            <h1>
                Promotion not found
            </h1>

            <p>
                The promotion link is invalid.
            </p>

            <a href="<?= e(SITE_URL) ?>">
                Go to Website
            </a>

        </div>

    </body>
    </html>
    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD PROMOTION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        whatsapp_number,
        button_link,
        status
    FROM promotions
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $id
]);

$promotion = $stmt->fetch(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| PROMOTION DOES NOT EXIST
|--------------------------------------------------------------------------
*/

if (!$promotion) {

    http_response_code(404);

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Promotion Not Found</title>

        <style>

            *{
                box-sizing:border-box;
            }

            body{
                margin:0;
                min-height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:20px;
                background:#111;
                color:#fff;
                font-family:Arial,Helvetica,sans-serif;
            }

            .box{
                width:100%;
                max-width:480px;
                padding:35px;
                text-align:center;
                background:#1b1b1b;
                border:1px solid rgba(255,255,255,.10);
                border-radius:18px;
            }

            h1{
                margin:0 0 10px;
                font-size:24px;
            }

            p{
                color:#aaa;
                line-height:1.6;
            }

            a{
                display:inline-block;
                margin-top:15px;
                padding:12px 18px;
                border-radius:10px;
                background:#fff;
                color:#111;
                text-decoration:none;
                font-weight:700;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h1>
                Promotion Not Found
            </h1>

            <p>
                This promotion may have been removed
                or is no longer available.
            </p>

            <a href="<?= e(SITE_URL) ?>">
                Go to Website
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| ONLY HANDLE WHATSAPP HERE
|--------------------------------------------------------------------------
*/

if ($type === 'whatsapp') {


    /*
    |--------------------------------------------------------------------------
    | GET WHATSAPP NUMBER
    |--------------------------------------------------------------------------
    */

    $whatsappNumber = trim(
        (string)(
            $promotion['whatsapp_number']
            ?? ''
        )
    );


    /*
    |--------------------------------------------------------------------------
    | REMOVE EVERYTHING EXCEPT NUMBERS
    |--------------------------------------------------------------------------
    |
    | Example:
    | +250 788 577 033
    |
    | becomes:
    |
    | 250788577033
    |
    */

    $whatsappNumber = preg_replace(
        '/[^0-9]/',
        '',
        $whatsappNumber
    ) ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATE WHATSAPP NUMBER
    |--------------------------------------------------------------------------
    */

    if ($whatsappNumber === '') {

        http_response_code(400);

        ?>

        <!DOCTYPE html>
        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>WhatsApp Unavailable</title>

            <style>

                *{
                    box-sizing:border-box;
                }

                body{
                    margin:0;
                    min-height:100vh;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    padding:20px;
                    background:#111;
                    color:#fff;
                    font-family:
                        Arial,
                        Helvetica,
                        sans-serif;
                }

                .box{

                    width:100%;
                    max-width:480px;

                    padding:35px;

                    text-align:center;

                    background:#1b1b1b;

                    border:
                        1px solid
                        rgba(255,255,255,.10);

                    border-radius:18px;

                }

                h1{
                    margin:0 0 10px;
                    font-size:24px;
                }

                p{
                    color:#aaa;
                    line-height:1.6;
                }

                a{

                    display:inline-block;

                    margin-top:15px;

                    padding:12px 18px;

                    border-radius:10px;

                    background:#fff;

                    color:#111;

                    text-decoration:none;

                    font-weight:700;

                }

            </style>

        </head>

        <body>

            <div class="box">

                <h1>
                    WhatsApp Unavailable
                </h1>

                <p>
                    This promotion does not have
                    a WhatsApp number configured yet.
                </p>

                <a href="<?= e(SITE_URL) ?>">
                    Back to Website
                </a>

            </div>

        </body>

        </html>

        <?php

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | COUNT WHATSAPP CLICK
    |--------------------------------------------------------------------------
    |
    | We increment the existing value.
    |
    */

    try {

        $update = $pdo->prepare("
            UPDATE promotions
            SET whatsapp_clicks =
                COALESCE(whatsapp_clicks, 0) + 1
            WHERE id = ?
            LIMIT 1
        ");

        $update->execute([
            $id
        ]);

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        | Even if counting fails, we still want the customer
        | to reach WhatsApp.
        |
        */
    }


    /*
    |--------------------------------------------------------------------------
    | WHATSAPP MESSAGE
    |--------------------------------------------------------------------------
    |
    | This message will already be typed in WhatsApp.
    |
    */

    $message =
        "Hello Imagination Nature Studio, "
        . "I am interested in the promotion: "
        . ($promotion['title'] ?? 'Promotion')
        . ".";


    /*
    |--------------------------------------------------------------------------
    | BUILD WHATSAPP URL
    |--------------------------------------------------------------------------
    */

    $whatsappUrl =
        'https://wa.me/'
        . $whatsappNumber
        . '?text='
        . rawurlencode($message);


    /*
    |--------------------------------------------------------------------------
    | REDIRECT TO WHATSAPP
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ' . $whatsappUrl,
        true,
        302
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| OTHER CLICK TYPES
|--------------------------------------------------------------------------
|
| If the type is not WhatsApp, send the visitor to
| the promotion button link.
|
*/

$buttonLink = trim(
    (string)(
        $promotion['button_link']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| DEFAULT LINK
|--------------------------------------------------------------------------
*/

if ($buttonLink === '') {

    $buttonLink = '/booking.php';

}


/*
|--------------------------------------------------------------------------
| HANDLE ABSOLUTE / RELATIVE URL
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '~^https?://~i',
        $buttonLink
    )
) {

    $redirectUrl = $buttonLink;

} else {

    $redirectUrl =
        rtrim(SITE_URL, '/')
        . '/'
        . ltrim(
            $buttonLink,
            '/'
        );

}


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    'Location: ' . $redirectUrl,
    true,
    302
);

exit;