<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Access Denied</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    * {
      font-family: 'Poppins', sans-serif;
      box-sizing: border-box;
    }
    body {
      background: #f9f9f9;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
      color: #333;
    }
    .box {
      text-align: center;
      border: 2px solid #f5d300;
      padding: 45px 50px;
      border-radius: 15px;
      background: #fff;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
      animation: fadeIn 0.5s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    h1 {
      color: #f5d300;
      margin-bottom: 12px;
      font-size: 28px;
      font-weight: 600;
    }
    p {
      margin-bottom: 25px;
      font-size: 16px;
    }
    a {
      display: inline-block;
      padding: 10px 20px;
      background: #f5d300;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      color: #000;
      transition: 0.3s ease;
    }
    a:hover {
      background: #ff8c00;
      color: #fff;
      transform: translateY(-2px);
    }
  </style>
</head>
<body>
  <div class="box">
    <h1>🚫 Access Denied</h1>
    <p>You don’t have permission to access this page.</p>
    <a href="/login.php">Go Back</a>
  </div>
</body>
</html>
