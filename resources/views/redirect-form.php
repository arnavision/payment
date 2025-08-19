<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>در حال انتقال به درگاه پرداخت</title>
    <style>
        .text-center {
            text-align: center;
        }

        .mt-2 {
            margin-top: 2em;
        }

        .spinner {
            margin: 100px auto 0;
            width: 70px;
            text-align: center;
        }

        .spinner > div {
            width: 18px;
            height: 18px;
            background-color: #333;
            border-radius: 100%;
            display: inline-block;
            -webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;
            animation: sk-bouncedelay 1.4s infinite ease-in-out both;
        }

        .spinner .bounce1 {
            -webkit-animation-delay: -0.32s;
            animation-delay: -0.32s;
        }

        .spinner .bounce2 {
            -webkit-animation-delay: -0.16s;
            animation-delay: -0.16s;
        }

        @-webkit-keyframes sk-bouncedelay {
            0%, 80%, 100% { -webkit-transform: scale(0) }
            40% { -webkit-transform: scale(1.0) }
        }

        @keyframes sk-bouncedelay {
            0%, 80%, 100% {
                -webkit-transform: scale(0);
                transform: scale(0);
            } 40% {
                  -webkit-transform: scale(1.0);
                  transform: scale(1.0);
              }
        }
    </style>
</head>
<body>
<div class="spinner">
    <div class="bounce1"></div>
    <div class="bounce2"></div>
    <div class="bounce3"></div>
</div>
<form class="text-center mt-2" name="MyForm" method="<?php echo htmlentities($method) ?>" action="<?php echo htmlentities($action) ?>">
    <p>در حال انتقال به درگاه پرداخت امن.</p>
    <br>
    <p><strong style="color: #e10000">در صورت وصل نشدن به درگاه پرداخت حتما vpn یا فیلترشکن خود را خاموش کنید.</strong></p>
    <br>
    <p>
        اگر در عرض
        <span id="countdown">10</span>
        ثانیه به صورت خودکار به صفحه پرداخت منتقل نشدید...
    </p>

    <?php foreach ($inputs as $name => $value): ?>
        <input type="hidden" name="<?php echo htmlentities($name) ?>" value="<?php echo htmlentities($value) ?>">
    <?php endforeach; ?>

    <button type="submit">اینجا کلیک کنید</button>
</form>
<script>

    document.MyForm.submit();

    // Total seconds to wait
    var seconds = 10;


    function countdown() {
        seconds = seconds - 1;
        if (seconds > 0) {
            // Update remaining seconds
            document.getElementById("countdown").innerHTML = seconds;
            // Count down using javascript
            window.setTimeout("countdown()", 1000);
        }
    }

    countdown();
</script>
</body>
</html>
