<!DOCTYPE html>
<html lang="en">
<head>
  <script>
    (function() {
      const savedTheme = localStorage.getItem('khadilu-theme') || 'theme-business-light';
      document.documentElement.className = savedTheme;
    })();
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Khadilu Phone Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: var(--bg);
      margin: 0;
      font-family: 'Inter', sans-serif;
    }
    .login-card {
      width: 100%;
      max-width: 400px;
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 32px;
      box-shadow: var(--shadow-lg);
    }
    .login-header {
      text-align: center;
      margin-bottom: 24px;
    }
    .login-logo {
      font-size: 2.5rem;
      margin-bottom: 8px;
    }
    .login-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--text);
      margin: 0;
    }
    .login-sub {
      color: var(--text-dim);
      font-size: 0.875rem;
      margin-top: 4px;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-header">
      <div class="login-logo">📱</div>
      <h1 class="login-title">Khadilu Phone Hub</h1>
      <p class="login-sub">Sign in to access your dashboard</p>
    </div>

    @if(session('error'))
      <div class="alert alert-danger" style="margin-bottom: 16px; font-size: 0.875rem; padding: 10px 14px; border-radius: 8px;">
        {{ session('error') }}
      </div>
    @endif

    @if($errors->any())
      <div class="alert alert-danger" style="margin-bottom: 16px; font-size: 0.875rem; padding: 10px 14px; border-radius: 8px;">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf
      <div class="form-group">
        <label for="username" class="form-label">Username</label>
        <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required autofocus placeholder="Enter your username">
      </div>

      <div class="form-group" style="margin-top: 16px;">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 24px; padding: 12px; font-weight: 700;">
        Sign In →
      </button>
    </form>
  </div>
</body>
</html>
