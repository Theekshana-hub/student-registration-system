<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= APP_NAME ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

   
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

   
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">

    <style>
   
    :root {
        --sidebar-width: 260px;
        --sidebar-bg: #0f172a;
        --sidebar-hover: #1e293b;
        --coral: #e11d48;
        --coral-soft: #fff1f2;
        --teal: #0d9488;
        --ink: #0f172a;
        --ink-soft: #64748b;
        --bg: #f1f5f9;
        --surface: #ffffff;
        --radius: 14px;
        --shadow: 0 1px 3px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.06);
    }

    * { box-sizing: border-box; }

    html, body {
        height: 100%;
    }

    body {
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        background: var(--bg);
        color: var(--ink);
        margin: 0;
        overflow-x: hidden;
    }

    #wrapper {
        display: flex;
        width: 100%;
        min-height: 100vh;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        min-width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        color: #e2e8f0;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform .3s cubic-bezier(.4,0,.2,1);
        z-index: 1000;
    }

   
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.35);
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 1.5rem 1.25rem;
        border-bottom: 1px solid rgba(255,255,255,.06);
    }

    .brand-icon {
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, #e11d48, #fb7185);
        border-radius: 12px;
        display: grid;
        place-items: center;
        font-size: 1.25rem;
        color: white;
        box-shadow: 0 4px 14px rgba(225,29,72,.4);
    }

    .brand-text h4 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: -.02em;
        color: #fff;
    }

    .brand-text span {
        font-size: .72rem;
        color: #94a3b8;
        font-weight: 500;
    }

    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 1rem 0.75rem 2rem;
    }

    .nav-section {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        padding: 1.1rem 0.85rem 0.4rem;
        margin-top: 0.3rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.7rem 0.9rem;
        border-radius: 11px;
        color: #cbd5e1;
        text-decoration: none;
        font-size: .9rem;
        font-weight: 500;
        transition: all .2s ease;
        margin-bottom: 2px;
        position: relative;
    }

    .nav-item i {
        font-size: 1.15rem;
        width: 22px;
        text-align: center;
        opacity: .85;
    }

    .nav-item:hover {
        background: var(--sidebar-hover);
        color: #fff;
    }

    .nav-item:hover i {
        opacity: 1;
    }

    .nav-item.active {
        background: linear-gradient(90deg, rgba(225,29,72,.18), rgba(225,29,72,.08));
        color: #fff;
        font-weight: 600;
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3.5px;
        height: 60%;
        background: var(--coral);
        border-radius: 0 4px 4px 0;
        box-shadow: 0 0 12px rgba(225,29,72,.5);
    }

    .nav-item.active i {
        color: var(--coral);
        opacity: 1;
    }

   
    #page-content-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        margin-left: var(--sidebar-width);
        min-height: 100vh;
        transition: margin-left .3s ease;
    }

    
    .top-navbar {
        background: rgba(255,255,255,.85);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(15,23,42,.06);
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 900;
        box-shadow: 0 1px 3px rgba(15,23,42,.03);
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .menu-toggle {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: var(--ink);
        display: grid;
        place-items: center;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all .2s ease;
    }

    .menu-toggle:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: var(--coral);
    }

    .page-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: -.02em;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .current-time {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: .82rem;
        color: var(--ink-soft);
        font-weight: 500;
        background: #f1f5f9;
        padding: 0.4rem 0.8rem;
        border-radius: 999px;
    }

    .current-time i {
        font-size: .9rem;
        color: var(--teal);
    }

    .user-btn {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        text-decoration: none;
        color: var(--ink);
        font-weight: 600;
        font-size: .9rem;
        padding: 0.3rem 0.5rem 0.3rem 0.3rem;
        border-radius: 999px;
        transition: all .2s ease;
    }

    .user-btn:hover {
        background: #f1f5f9;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e11d48, #fb7185);
        color: white;
        display: grid;
        place-items: center;
        font-size: 1rem;
        box-shadow: 0 3px 10px rgba(225,29,72,.3);
    }

    .user-dropdown {
        border: none;
        box-shadow: 0 10px 40px rgba(15,23,42,.12);
        border-radius: 14px;
        padding: 0.5rem;
        min-width: 180px;
        margin-top: 0.5rem !important;
    }

    .user-dropdown .dropdown-item {
        border-radius: 9px;
        padding: 0.55rem 0.9rem;
        font-size: .875rem;
        font-weight: 500;
        transition: all .15s ease;
    }

    .user-dropdown .dropdown-item:hover {
        background: #f1f5f9;
    }

    .user-dropdown .dropdown-item.text-danger:hover {
        background: #fff1f2;
        color: #e11d48 !important;
    }

  
    .main-content {
        padding: 1.5rem 1.75rem 2.5rem;
        flex: 1;
        overflow-y: auto;
    }

    
    #wrapper.toggled .sidebar {
        transform: translateX(calc(-1 * var(--sidebar-width)));
    }

    #wrapper.toggled #page-content-wrapper {
        margin-left: 0;
    }

  
    @media (max-width: 991.98px) {
        .sidebar {
            transform: translateX(-100%);
        }

        #page-content-wrapper {
            margin-left: 0;
        }

        
        #wrapper.toggled .sidebar {
            transform: translateX(0);
        }

        #wrapper.toggled::after {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.4);
            z-index: 999;
        }

        .main-content {
            padding: 1.25rem 1rem 2rem;
        }
    }

    @media (max-width: 575.98px) {
        .current-time span {
            display: none;
        }
        .user-name {
            display: none;
        }
    }
    </style>
</head>
<body>
<div class="d-flex" id="wrapper">