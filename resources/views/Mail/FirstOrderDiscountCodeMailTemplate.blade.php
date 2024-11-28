<!DOCTYPE html>
<html>
<head>
    <title>Enjoy Your First Order Discount</title>
    <style>
        .coupon-code {
            font-size: 24px;
            color: #4CAF50;
            font-weight: bold;
            margin: 20px 0;
        }
        .cta {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
        .cta:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>Welcome to {{$shopName}}!</h1>
    <p>We’re thrilled to have you with us. As a token of our appreciation, here’s your exclusive discount coupon for your first order:</p>
    
    <div class="coupon-code">
        {{ $couponCode }}
    </div>
    
    <p>Use this coupon at checkout to enjoy your discount. Act quickly—this coupon is valid for a limited time only!</p>
    
    <a href="{{$shopName}}" class="cta">Shop Now</a>
    
    <p>Happy shopping!</p>
    <p>Best regards,</p>
    <p>The {{$shopName}} Team</p>
</body>
</html>
