<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2933;
        }
        .wrapper {
            padding: 24px;
        }
        .card {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
        }
        h1 {
            font-size: 20px;
            margin: 0 0 12px;
        }
        p {
            margin: 0 0 12px;
            line-height: 1.5;
        }
        ul {
            margin: 0;
            padding-left: 18px;
        }
        li {
            margin-bottom: 6px;
        }
        .footer {
            margin-top: 18px;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <h1>{{ $title }}</h1>

            @if (count($lines) > 1)
                <ul>
                    @foreach ($lines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @else
                <p>{{ $messageText }}</p>
            @endif

            <p class="footer">Sent from Smart Learning Tracker</p>
        </div>
    </div>
</body>
</html>
