<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In - Vanguard Motors</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #0a0a0a; /* main dark tone from index */
      color: white;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Rubik', sans-serif;
    }

    .form-box {
      background-color: #2A2D46; /* dark blue-gray */
      border: 1px solid #333;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
      padding: 30px;
      width: 100%;
      max-width: 400px;
    }

    .form-label {
      color: #f1f1f1;
    }

    .form-control {
      background-color: #3b3b3b;
      border: 1px solid #555;
      color: #9a6fe0;
    }

    .form-control::placeholder {
      color: #aaa;
    }

    .btn-primary {
      background-color: #cb1a1a; /* white button */
      color: #f1f1f1;
      border: none;
      transition: 0.3s;
    }

    .btn-primary:hover {
      background-color: #dcdcdc;
      color:#dc0c0c 
    }

    .form-text {
      color: #ccc !important;
    }

    .extra-links {
      text-align: center;
      margin-top: 15px;
    }

    .extra-links a {
      color: #007bff;
      text-decoration: none;
      font-weight: 500;
    }

    .extra-links a:hover {
      text-decoration: underline;
      color: #66b3ff;
    }

    .login-title {
      color: #ffffff; /* white title */
      text-align: center;
      margin-bottom: 25px;
      font-weight: 600;
    }
  </style>
</head>

<body>
  <div class="form-box">
    <h2 class="login-title">Log In</h2>

<form action="validar.php" method="POST">
  <div class="mb-3">
    <label for="emailInput" class="form-label">Email address</label>
    <input type="email" class="form-control" id="emailInput" 
           name="email" placeholder="Enter your email" required>
  </div>

  <div class="mb-3">
    <label for="passwordInput" class="form-label">Password</label>
    <input type="password" class="form-control" id="passwordInput" 
           name="password" placeholder="Enter your password" required>
  </div>

  <button type="submit" class="btn btn-primary w-100">Sign In</button>
<?php if (isset($_GET['error'])): ?>
    <p style="color:red; text-align:center;">
        <?php 
            if ($_GET['error'] == 'invalid') {
                echo "Usuario o contraseña incorrectos";
            } elseif ($_GET['error'] == 'empty') {
                echo "Por favor llena todos los campos";
            }
        ?>
    </p>
<?php endif; ?>

</form>


    <div class="extra-links">
      <p class="mt-3">Don’t have an account? <a href="register.html">Register here</a></p>
      <p><a href="index.html">← Back to home</a></p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
