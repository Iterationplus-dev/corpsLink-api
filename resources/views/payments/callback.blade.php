<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CorpersLink — Payment</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f6f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }
        .card {
            max-width: 360px;
            width: 100%;
            text-align: center;
        }
        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #e3f3ea;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg { width: 30px; height: 30px; }
        h1 {
            font-size: 20px;
            font-weight: 800;
            color: #16233c;
            margin: 0 0 8px;
        }
        p {
            font-size: 14.5px;
            line-height: 1.55;
            color: #5b6472;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 6L9 17L4 12" stroke="#1c8a4c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1>You're all set</h1>
        <p>You can close this window and return to the CorpersLink app to see your booking.</p>
    </div>
</body>
</html>
