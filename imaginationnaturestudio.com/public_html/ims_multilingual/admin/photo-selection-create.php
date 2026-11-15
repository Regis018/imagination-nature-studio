<?php
declare(strict_types=1);

/**
 * ============================================================
 * IMAGINATION NATURE STUDIO
 * PHOTO SELECTION SYSTEM
 *
 * Admin - Create Photo Selection Gallery
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$message = '';
$error   = '';

/*
|--------------------------------------------------------------------------
| Create Gallery
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clientName = trim($_POST['client_name'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $bookingId = trim($_POST['booking_id'] ?? '');

    $pricePerPhoto = (float)($_POST['price_per_photo'] ?? 3000);

    $durationMinutes = (int)($_POST['duration_minutes'] ?? 120);


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($clientName === '') {

        $error = 'Client name is required.';

    } elseif ($title === '') {

        $error = 'Gallery title is required.';

    } elseif ($pricePerPhoto <= 0) {

        $error = 'Price per photo must be greater than 0.';

    } elseif ($durationMinutes <= 0) {

        $error = 'Selection duration must be greater than 0 minutes.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Generate secure private gallery token
            |--------------------------------------------------------------------------
            */

            $accessToken = bin2hex(random_bytes(32));


            /*
            |--------------------------------------------------------------------------
            | Booking ID
            |--------------------------------------------------------------------------
            */

            $bookingIdValue = null;

            if ($bookingId !== '') {
                $bookingIdValue = (int)$bookingId;
            }


            /*
            |--------------------------------------------------------------------------
            | Insert Gallery
            |--------------------------------------------------------------------------
            */

            $sql = "
                INSERT INTO photo_galleries
                (
                    booking_id,
                    client_name,
                    client_phone,
                    title,
                    access_token,
                    price_per_photo,
                    duration_minutes,
                    status
                )
                VALUES
                (
                    :booking_id,
                    :client_name,
                    :client_phone,
                    :title,
                    :access_token,
                    :price_per_photo,
                    :duration_minutes,
                    'draft'
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([

                ':booking_id'       => $bookingIdValue,

                ':client_name'     => $clientName,

                ':client_phone'    => $clientPhone,

                ':title'           => $title,

                ':access_token'    => $accessToken,

                ':price_per_photo' => $pricePerPhoto,

                ':duration_minutes'=> $durationMinutes,

            ]);


            /*
            |--------------------------------------------------------------------------
            | Get new gallery ID
            |--------------------------------------------------------------------------
            */

            $galleryId = (int)$pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Redirect to upload page
            |--------------------------------------------------------------------------
            */

            header(
                'Location: photo-selection-upload.php?id='
                . $galleryId
            );

            exit;


        } catch (Throwable $e) {

            error_log(
                'Photo Selection Gallery Error: '
                . $e->getMessage()
            );

            $error = 'Something went wrong while creating the gallery.';
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Create Photo Selection —
Imagination Nature Studio
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background: #f5f6f8;

    color: #17181b;
}


.container {

    max-width: 850px;

    margin: 40px auto;

    padding: 20px;
}


.card {

    background: #ffffff;

    border-radius: 18px;

    padding: 30px;

    box-shadow:
        0 10px 35px rgba(0,0,0,.06);

    border: 1px solid #e5e7eb;
}


.header {

    margin-bottom: 25px;
}


.header h1 {

    margin: 0;

    font-size: 27px;
}


.header p {

    margin-top: 7px;

    color: #70757d;

    font-size: 14px;
}


.form-group {

    margin-bottom: 20px;
}


label {

    display: block;

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 7px;
}


input {

    width: 100%;

    padding: 13px 14px;

    border: 1px solid #dfe2e7;

    border-radius: 10px;

    font-size: 15px;

    outline: none;
}


input:focus {

    border-color: #111;

}


.row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


.price-box {

    background: #f7f7f8;

    border-radius: 12px;

    padding: 15px;

    margin-bottom: 20px;
}


.price-box strong {

    font-size: 22px;
}


.price-box span {

    color: #70757d;

    font-size: 13px;
}


.notice {

    background: #f5f8ff;

    border: 1px solid #dce6ff;

    border-radius: 12px;

    padding: 14px;

    font-size: 13px;

    color: #4d5562;

    margin-bottom: 20px;
}


.error {

    background: #fff1f1;

    border: 1px solid #ffd1d1;

    color: #b42318;

    padding: 13px;

    border-radius: 10px;

    margin-bottom: 20px;

}


button {

    width: 100%;

    padding: 14px;

    border: 0;

    border-radius: 11px;

    background: #111;

    color: #fff;

    font-size: 15px;

    font-weight: 700;

    cursor: pointer;
}


button:hover {

    background: #222;
}


@media(max-width:650px) {

    .row {

        grid-template-columns: 1fr;

    }

    .container {

        margin: 15px auto;

        padding: 12px;

    }

    .card {

        padding: 20px;

    }

}

</style>

</head>


<body>


<div class="container">

    <div class="card">


        <div class="header">

            <h1>
                📸 Create Photo Selection
            </h1>

            <p>
                Create a private gallery where your client
                can select photos for editing.
            </p>

        </div>


        <?php if ($error !== ''): ?>

            <div class="error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="form-group">

                <label>
                    Client Name
                </label>

                <input
                    type="text"
                    name="client_name"
                    placeholder="Example: Jean & Alice"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Client Phone
                </label>

                <input
                    type="text"
                    name="client_phone"
                    placeholder="+250 7..."
                >

            </div>


            <div class="form-group">

                <label>
                    Gallery Title
                </label>

                <input
                    type="text"
                    name="title"
                    value="Photo Selection"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Booking ID
                    <small style="color:#888;">
                        (Optional for now)
                    </small>
                </label>

                <input
                    type="number"
                    name="booking_id"
                    placeholder="Example: 36"
                >

            </div>


            <div class="row">


                <div class="form-group">

                    <label>
                        Price Per Photo
                    </label>

                    <input
                        type="number"
                        name="price_per_photo"
                        value="3000"
                        min="1"
                        step="1"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Selection Time
                    </label>

                    <input
                        type="number"
                        name="duration_minutes"
                        value="120"
                        min="1"
                        required
                    >

                </div>


            </div>


            <div class="price-box">

                <strong>
                    3,000 RWF
                </strong>

                <span>
                    / selected photo
                </span>

            </div>


            <div class="notice">

                🔒 <strong>Private Selection</strong><br><br>

                The client will receive a private gallery
                link. The selection session will remain
                active for <strong>2 hours</strong>.

                Original photos will not be exposed to the
                client.

            </div>


            <button type="submit">

                Create Photo Gallery →

            </button>


        </form>


    </div>

</div>


</body>

</html>