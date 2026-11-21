<?php
/*
|--------------------------------------------------------------------------
| Landing Page Pública - Comandaria Express Multitenant
|--------------------------------------------------------------------------
| Arquivo seguro para publicar separadamente, sem sobrescrever o index.php
| operacional do atendimento. Ajuste os links abaixo conforme a estrutura
| final do domínio.
*/

$whatsappNumero = '5571993143374';
$mensagemFree = rawurlencode('Olá, Bacuri Digital! Quero começar no plano FREE da Comandaria Express.');
$mensagemPro  = rawurlencode('Olá, Bacuri Digital! Quero saber mais sobre o plano PRO da Comandaria Express.');
$mensagemDemo = rawurlencode('Olá, Bacuri Digital! Quero conhecer melhor a Comandaria Express.');

$linkWhatsappFree = "https://wa.me/{$whatsappNumero}?text={$mensagemFree}";
$linkWhatsappPro  = "https://wa.me/{$whatsappNumero}?text={$mensagemPro}";
$linkWhatsappDemo = "https://wa.me/{$whatsappNumero}?text={$mensagemDemo}";

// Ajuste se o login estiver em outro caminho.
$linkLogin = 'login.php';

$anoAtual = date('Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d0d0f">
    <meta name="description" content="Comandaria Express: sistema multitenant para bares, lanchonetes e restaurantes controlarem pedidos, mesas, cozinha, caixa e pedidos por QR Code.">

    <title>Comandaria Express | Pedidos, Mesas, Cozinha e Caixa</title>

    <meta property="og:title" content="Comandaria Express | Gestão de pedidos para estabelecimentos">
    <meta property="og:description" content="Comece no plano FREE e evolua para o PRO. Controle mesas, pedidos, cozinha, caixa e atendimento em um só sistema.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:image" content="images/comandaria_logo_og.png">

    <link rel="icon" href="images/topo_comandaria_shake.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #0b0b0d;
            --bg-soft: #121217;
            --card: rgba(255, 255, 255, 0.065);
            --card-strong: rgba(255, 255, 255, 0.1);
            --line: rgba(255, 255, 255, 0.12);
            --text: #f8f8fa;
            --muted: rgba(255, 255, 255, 0.72);
            --muted-2: rgba(255, 255, 255, 0.56);
            --yellow: #ffc107;
            --yellow-strong: #ffcf33;
            --green: #20c997;
            --green-soft: rgba(32, 201, 151, 0.16);
            --red-soft: rgba(255, 98, 98, 0.14);
            --shadow: 0 24px 80px rgba(0, 0, 0, 0.42);
            --radius-xl: 30px;
            --radius-lg: 22px;
            --radius-md: 16px;
            --container: 1180px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(255, 193, 7, 0.16), transparent 32%),
                radial-gradient(circle at 88% 10%, rgba(32, 201, 151, 0.14), transparent 28%),
                radial-gradient(circle at 50% 80%, rgba(255, 255, 255, 0.05), transparent 32%),
                var(--bg);
            font-family: 'Inter', Arial, sans-serif;
            line-height: 1.5;
        }

        a {
            color: inherit;
        }

        .container {
            width: min(calc(100% - 32px), var(--container));
            margin: 0 auto;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(18px);
            background: rgba(11, 11, 13, 0.78);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .navbar {
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            min-width: 0;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, rgba(255, 193, 7, 0.95), rgba(255, 152, 0, 0.95));
            color: #111;
            font-size: 23px;
            font-weight: 900;
            box-shadow: 0 14px 40px rgba(255, 193, 7, 0.22);
            flex: 0 0 auto;
        }

        .brand-copy {
            display: grid;
            gap: 0;
            min-width: 0;
        }

        .brand-copy strong {
            display: block;
            font-size: 16px;
            letter-spacing: -0.02em;
            white-space: nowrap;
        }

        .brand-copy span {
            color: var(--muted-2);
            font-size: 12px;
            white-space: nowrap;
        }

        .menu {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-left: auto;
        }

        .menu a {
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .menu a:hover {
            color: #fff;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            transition: transform .2s ease, border-color .2s ease, background .2s ease, color .2s ease, box-shadow .2s ease;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--yellow), var(--yellow-strong));
            color: #111;
            box-shadow: 0 20px 45px rgba(255, 193, 7, 0.24);
        }

        .btn-primary:hover {
            box-shadow: 0 24px 52px rgba(255, 193, 7, 0.32);
        }

        .btn-secondary {
            border-color: rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .btn-secondary:hover {
            border-color: rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.11);
        }

        .btn-ghost {
            border-color: rgba(255, 255, 255, 0.14);
            color: #fff;
            background: transparent;
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .mobile-toggle {
            display: none;
            width: 46px;
            height: 46px;
            border-radius: 15px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            font-size: 22px;
            cursor: pointer;
        }

        .hero {
            padding: 76px 0 66px;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.04fr) minmax(390px, .96fr);
            gap: 46px;
            align-items: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 9px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 193, 7, 0.26);
            background: rgba(255, 193, 7, 0.1);
            color: #ffe79a;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 24px;
        }

        .eyebrow::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--yellow);
            box-shadow: 0 0 0 6px rgba(255, 193, 7, 0.13);
        }

        h1, h2, h3, p {
            margin-top: 0;
        }

        h1 {
            max-width: 850px;
            font-size: clamp(42px, 6vw, 74px);
            line-height: 0.98;
            letter-spacing: -0.065em;
            margin-bottom: 24px;
        }

        .hero-copy p {
            max-width: 720px;
            color: var(--muted);
            font-size: clamp(17px, 2vw, 20px);
            margin-bottom: 28px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 26px;
        }

        .quick-highlights {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .highlight-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.055);
            border-radius: 999px;
            padding: 9px 13px;
            color: rgba(255,255,255,0.82);
            font-size: 13px;
            font-weight: 700;
        }

        .hero-panel {
            position: relative;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(255,255,255,0.14);
            background:
                linear-gradient(180deg, rgba(255,255,255,0.11), rgba(255,255,255,0.055)),
                rgba(255,255,255,0.04);
            padding: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .hero-panel::before {
            content: '';
            position: absolute;
            inset: -18% auto auto 60%;
            width: 280px;
            height: 280px;
            background: rgba(255, 193, 7, 0.18);
            filter: blur(18px);
            border-radius: 999px;
        }

        .panel-head {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }

        .panel-head small {
            display: block;
            color: var(--muted-2);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 5px;
        }

        .panel-head strong {
            font-size: 23px;
            letter-spacing: -0.03em;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 9px 12px;
            border: 1px solid rgba(32,201,151,.32);
            background: rgba(32,201,151,.14);
            color: #aef6df;
            font-size: 12px;
            font-weight: 900;
        }

        .live-pill::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--green);
        }

        .dashboard-metrics {
            position: relative;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .metric {
            min-height: 112px;
            padding: 17px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.11);
            background: rgba(10, 10, 13, 0.56);
        }

        .metric span {
            display: block;
            color: var(--muted-2);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .metric strong {
            display: block;
            font-size: 28px;
            line-height: 1;
            letter-spacing: -0.045em;
            margin-bottom: 8px;
        }

        .metric em {
            font-style: normal;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .queue {
            position: relative;
            display: grid;
            gap: 11px;
            padding: 16px;
            border-radius: 22px;
            border: 1px solid rgba(255,255,255,0.11);
            background: rgba(10, 10, 13, 0.62);
        }

        .queue-row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 16px;
            background: rgba(255,255,255,0.055);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .queue-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: rgba(255,193,7,0.14);
            color: #ffe08a;
            font-size: 18px;
        }

        .queue-row strong {
            display: block;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .queue-row small {
            color: var(--muted-2);
            font-size: 12px;
            font-weight: 600;
        }

        .badge {
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(32,201,151,.16);
            border: 1px solid rgba(32,201,151,.25);
            color: #b9f5e4;
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }

        .badge.warning {
            background: rgba(255,193,7,.15);
            border-color: rgba(255,193,7,.26);
            color: #ffe79a;
        }

        .section {
            padding: 78px 0;
        }

        .section-head {
            max-width: 820px;
            margin-bottom: 34px;
        }

        .section-kicker {
            display: inline-flex;
            margin-bottom: 14px;
            color: #ffe08a;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .11em;
            font-weight: 900;
        }

        h2 {
            font-size: clamp(32px, 4vw, 52px);
            line-height: 1.04;
            letter-spacing: -0.05em;
            margin-bottom: 16px;
        }

        .section-head p {
            color: var(--muted);
            font-size: 18px;
            margin-bottom: 0;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .feature-card,
        .benefit-card,
        .audience-card,
        .plan-card,
        .value-card {
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255,255,255,0.11);
            background: rgba(255,255,255,0.055);
            box-shadow: 0 18px 45px rgba(0,0,0,.18);
        }

        .feature-card {
            padding: 22px;
            min-height: 228px;
        }

        .card-icon {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: rgba(255,193,7,0.14);
            border: 1px solid rgba(255,193,7,0.18);
            font-size: 24px;
            margin-bottom: 18px;
        }

        .feature-card h3,
        .benefit-card h3,
        .audience-card h3,
        .plan-card h3,
        .value-card h3 {
            font-size: 19px;
            line-height: 1.15;
            letter-spacing: -0.025em;
            margin-bottom: 10px;
        }

        .feature-card p,
        .benefit-card p,
        .audience-card p,
        .value-card p {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 0;
        }

        .split {
            display: grid;
            grid-template-columns: minmax(0, .92fr) minmax(0, 1.08fr);
            gap: 24px;
            align-items: stretch;
        }

        .benefit-stage {
            border-radius: var(--radius-xl);
            border: 1px solid rgba(255,255,255,.11);
            padding: 28px;
            background:
                radial-gradient(circle at top right, rgba(255,193,7,.16), transparent 30%),
                rgba(255,255,255,.055);
            display: grid;
            align-content: space-between;
            gap: 24px;
        }

        .benefit-stage strong.big {
            display: block;
            max-width: 580px;
            font-size: clamp(30px, 4vw, 46px);
            line-height: 1.03;
            letter-spacing: -0.055em;
        }

        .mini-list {
            display: grid;
            gap: 12px;
        }

        .mini-list div {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px 14px;
            border-radius: 17px;
            border: 1px solid rgba(255,255,255,0.09);
            background: rgba(8,8,10,.42);
            color: rgba(255,255,255,.88);
            font-size: 14px;
            font-weight: 700;
        }

        .mini-list div::before {
            content: '✓';
            width: 24px;
            height: 24px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: rgba(32,201,151,.18);
            color: #9df3d8;
            font-size: 13px;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .benefit-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .benefit-card {
            padding: 22px;
            min-height: 212px;
        }

        .benefit-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 14px;
            background: rgba(255,193,7,.14);
            color: #ffe08a;
            font-size: 14px;
            font-weight: 900;
            margin-bottom: 18px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            align-items: stretch;
        }

        .plan-card {
            padding: 28px;
            position: relative;
            overflow: hidden;
        }

        .plan-card.pro {
            border-color: rgba(255,193,7,.38);
            background:
                radial-gradient(circle at top right, rgba(255,193,7,.18), transparent 34%),
                rgba(255,255,255,0.072);
        }

        .plan-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            color: rgba(255,255,255,.88);
            margin-bottom: 16px;
        }

        .plan-card.pro .plan-label {
            background: rgba(255,193,7,.14);
            border-color: rgba(255,193,7,.25);
            color: #ffe79a;
        }

        .plan-price {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            margin: 16px 0 18px;
        }

        .plan-price strong {
            font-size: clamp(38px, 4vw, 56px);
            line-height: 0.95;
            letter-spacing: -0.06em;
        }

        .plan-price span {
            color: var(--muted);
            font-size: 15px;
            font-weight: 700;
            padding-bottom: 6px;
        }

        .plan-card > p {
            color: var(--muted);
            font-size: 16px;
            min-height: 48px;
            margin-bottom: 22px;
        }

        .plan-list {
            display: grid;
            gap: 12px;
            margin: 0 0 24px;
            padding: 0;
            list-style: none;
        }

        .plan-list li {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            color: rgba(255,255,255,.9);
            font-size: 15px;
            font-weight: 650;
        }

        .plan-list li::before {
            content: '✓';
            width: 23px;
            height: 23px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: rgba(32,201,151,.16);
            color: #9df3d8;
            font-size: 12px;
            font-weight: 900;
            flex: 0 0 auto;
            margin-top: 1px;
        }

        .plan-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .audience-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .audience-card {
            padding: 24px;
            min-height: 220px;
        }

        .audience-tag {
            display: inline-flex;
            margin-bottom: 15px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,.075);
            border: 1px solid rgba(255,255,255,.11);
            color: rgba(255,255,255,.84);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .value-card {
            padding: 22px;
            min-height: 190px;
        }

        .value-number {
            display: block;
            color: rgba(255,193,7,.94);
            font-size: 14px;
            font-weight: 900;
            letter-spacing: .12em;
            margin-bottom: 18px;
        }

        .cta {
            padding-top: 20px;
            padding-bottom: 90px;
        }

        .cta-box {
            border-radius: 36px;
            border: 1px solid rgba(255,193,7,.25);
            background:
                radial-gradient(circle at 15% 30%, rgba(255,193,7,.22), transparent 31%),
                radial-gradient(circle at 90% 80%, rgba(32,201,151,.16), transparent 28%),
                rgba(255,255,255,.07);
            padding: clamp(28px, 5vw, 58px);
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 26px;
        }

        .cta-box h2 {
            margin-bottom: 12px;
            max-width: 780px;
        }

        .cta-box p {
            color: var(--muted);
            font-size: 18px;
            margin-bottom: 0;
            max-width: 760px;
        }

        .cta-actions {
            display: grid;
            gap: 12px;
            justify-items: stretch;
        }

        footer {
            border-top: 1px solid rgba(255,255,255,.08);
            padding: 28px 0 38px;
            color: var(--muted-2);
            font-size: 13px;
        }

        .footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .footer-row strong {
            color: rgba(255,255,255,.92);
        }

        @media (max-width: 1120px) {
            .menu {
                display: none;
            }

            .mobile-toggle {
                display: inline-grid;
                place-items: center;
            }

            .navbar.open {
                flex-wrap: wrap;
                padding-bottom: 16px;
            }

            .navbar.open .menu {
                order: 4;
                width: 100%;
                display: grid;
                gap: 10px;
                margin: 0;
                padding: 16px;
                border-radius: 20px;
                border: 1px solid rgba(255,255,255,.1);
                background: rgba(255,255,255,.055);
            }

            .navbar.open .menu a {
                padding: 8px 0;
            }

            .hero-grid,
            .split,
            .cta-box {
                grid-template-columns: 1fr;
            }

            .feature-grid,
            .values-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .cta-actions {
                display: flex;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 840px) {
            .hero {
                padding-top: 54px;
            }

            .hero-grid {
                gap: 30px;
            }

            .plans-grid,
            .audience-grid,
            .benefit-grid {
                grid-template-columns: 1fr;
            }

            .header-actions .btn-ghost {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .container {
                width: min(calc(100% - 22px), var(--container));
            }

            .navbar {
                min-height: 74px;
                gap: 10px;
            }

            .brand-mark {
                width: 44px;
                height: 44px;
                border-radius: 15px;
            }

            .brand-copy strong {
                font-size: 14px;
            }

            .brand-copy span {
                font-size: 11px;
            }

            .header-actions .btn-primary {
                display: none;
            }

            h1 {
                font-size: clamp(38px, 12vw, 54px);
                margin-bottom: 18px;
            }

            .hero-copy p,
            .section-head p,
            .cta-box p {
                font-size: 16px;
            }

            .hero-actions,
            .plan-actions,
            .cta-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
            }

            .dashboard-metrics,
            .feature-grid,
            .values-grid {
                grid-template-columns: 1fr;
            }

            .hero-panel,
            .benefit-stage,
            .plan-card,
            .cta-box {
                padding: 20px;
            }

            .panel-head {
                display: grid;
            }

            .queue-row {
                grid-template-columns: auto 1fr;
            }

            .queue-row .badge {
                grid-column: 1 / -1;
                justify-self: start;
            }

            .section {
                padding: 62px 0;
            }

            .cta {
                padding-bottom: 72px;
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="container">
        <div class="navbar" id="navbar">
            <a class="brand" href="#inicio" aria-label="Comandaria Express">
                <span class="brand-mark">C</span>
                <span class="brand-copy">
                    <strong>Comandaria Express</strong>
                    <span>Bacuri Digital</span>
                </span>
            </a>

            <nav class="menu" aria-label="Navegação principal">
                <a href="#funcionalidades">Funcionalidades</a>
                <a href="#diferenciais">Diferenciais</a>
                <a href="#planos">Planos</a>
                <a href="#publico">Para quem é</a>
            </nav>

            <div class="header-actions">
                <a class="btn btn-ghost" href="<?= htmlspecialchars($linkLogin, ENT_QUOTES, 'UTF-8') ?>">Entrar</a>
                <a class="btn btn-primary" href="<?= htmlspecialchars($linkWhatsappFree, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Começar grátis</a>
                <button class="mobile-toggle" id="mobileToggle" type="button" aria-label="Abrir menu" aria-expanded="false">☰</button>
            </div>
        </div>
    </div>
</header>

<main id="inicio">
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <div class="eyebrow">Plano FREE disponível • Evolução para PRO</div>
                <h1>Pedidos, mesas, cozinha e caixa em um só sistema.</h1>
                <p>
                    A Comandaria Express foi criada para bares, lanchonetes, hamburguerias e restaurantes
                    organizarem a operação com mais velocidade, menos retrabalho e uma experiência moderna
                    para equipe e clientes.
                </p>

                <div class="hero-actions">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($linkWhatsappFree, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Começar no FREE</a>
                    <a class="btn btn-secondary" href="#planos">Ver planos</a>
                    <a class="btn btn-ghost" href="<?= htmlspecialchars($linkLogin, ENT_QUOTES, 'UTF-8') ?>">Acessar sistema</a>
                </div>

                <div class="quick-highlights" aria-label="Destaques">
                    <span class="highlight-chip">🧾 Atendimento ágil</span>
                    <span class="highlight-chip">🍳 Cozinha em tempo real</span>
                    <span class="highlight-chip">💰 Caixa integrado</span>
                    <span class="highlight-chip">📱 QR Code por mesa</span>
                </div>
            </div>

            <aside class="hero-panel" aria-label="Prévia da operação">
                <div class="panel-head">
                    <div>
                        <small>Visão operacional</small>
                        <strong>Painel Comandaria</strong>
                    </div>
                    <span class="live-pill">Operação ativa</span>
                </div>

                <div class="dashboard-metrics">
                    <article class="metric">
                        <span>Plano FREE</span>
                        <strong>10</strong>
                        <em>mesas disponíveis</em>
                    </article>
                    <article class="metric">
                        <span>Pedidos mensais</span>
                        <strong>100</strong>
                        <em>limite inicial</em>
                    </article>
                    <article class="metric">
                        <span>Cozinha</span>
                        <strong>05</strong>
                        <em>itens em preparo</em>
                    </article>
                    <article class="metric">
                        <span>Caixa</span>
                        <strong>R$ 186</strong>
                        <em>conta em andamento</em>
                    </article>
                </div>

                <div class="queue">
                    <div class="queue-row">
                        <span class="queue-icon">🍔</span>
                        <div>
                            <strong>Mesa 07 • Pedido #128</strong>
                            <small>2 hambúrgueres, 1 batata e observação enviada</small>
                        </div>
                        <span class="badge warning">Preparando</span>
                    </div>
                    <div class="queue-row">
                        <span class="queue-icon">📱</span>
                        <div>
                            <strong>QR Code da mesa liberado</strong>
                            <small>Cliente pode acompanhar ou realizar pedido público</small>
                        </div>
                        <span class="badge">Ativo</span>
                    </div>
                    <div class="queue-row">
                        <span class="queue-icon">💳</span>
                        <div>
                            <strong>Conta pronta para fechamento</strong>
                            <small>Dinheiro, Pix, cartão ou prazo</small>
                        </div>
                        <span class="badge">Caixa</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="section" id="funcionalidades">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">Funcionalidades</span>
                <h2>Uma plataforma pensada para o fluxo real do atendimento.</h2>
                <p>
                    Do lançamento do pedido ao fechamento da conta, a Comandaria Express centraliza a operação
                    e reduz falhas entre atendimento, cozinha e caixa.
                </p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="card-icon">🧾</div>
                    <h3>Atendimento e comandas</h3>
                    <p>Abertura de mesas e clientes avulsos, montagem de carrinho e envio rápido de pedidos.</p>
                </article>
                <article class="feature-card">
                    <div class="card-icon">🪑</div>
                    <h3>Gestão de mesas</h3>
                    <p>Controle visual da ocupação, valores em aberto e fluxo de atendimento por mesa.</p>
                </article>
                <article class="feature-card">
                    <div class="card-icon">🍳</div>
                    <h3>Painel da cozinha</h3>
                    <p>Pedidos em preparo, atualização de status e acompanhamento mais claro da produção.</p>
                </article>
                <article class="feature-card">
                    <div class="card-icon">💰</div>
                    <h3>Caixa e fechamento</h3>
                    <p>Conferência de contas, impressão, formas de pagamento e fechamento com mais controle.</p>
                </article>
                <article class="feature-card">
                    <div class="card-icon">📱</div>
                    <h3>Pedidos por QR Code</h3>
                    <p>Camada pública para mesas, ideal para self-order e experiências mais modernas.</p>
                </article>
                <article class="feature-card">
                    <div class="card-icon">🍔</div>
                    <h3>Produtos e adicionais</h3>
                    <p>Cadastro de itens, categorias, observações, adicionais monetários e regras de preparo.</p>
                </article>
                <article class="feature-card">
                    <div class="card-icon">📊</div>
                    <h3>Relatórios operacionais</h3>
                    <p>Visão de vendas, produtos, pagamentos e dados úteis para decisões do estabelecimento.</p>
                </article>
                <article class="feature-card">
                    <div class="card-icon">🏢</div>
                    <h3>Estrutura multitenant</h3>
                    <p>Empresas separadas por conta, limites por plano e operação pronta para escalar.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="diferenciais">
        <div class="container split">
            <div class="benefit-stage">
                <div>
                    <span class="section-kicker">Diferenciais</span>
                    <strong class="big">Menos improviso. Mais fluxo, controle e clareza.</strong>
                </div>

                <div class="mini-list">
                    <div>Atendimento conectado à cozinha e ao caixa</div>
                    <div>Operação adaptada para desktop e celular</div>
                    <div>Plano gratuito para iniciar sem barreira</div>
                    <div>Upgrade PRO quando o volume crescer</div>
                </div>
            </div>

            <div class="benefit-grid">
                <article class="benefit-card">
                    <span class="benefit-index">01</span>
                    <h3>Centralização</h3>
                    <p>Pedidos, mesas, pagamentos e relatórios reunidos no mesmo ambiente.</p>
                </article>
                <article class="benefit-card">
                    <span class="benefit-index">02</span>
                    <h3>Agilidade</h3>
                    <p>Menos troca manual de informação entre balcão, cozinha e fechamento.</p>
                </article>
                <article class="benefit-card">
                    <span class="benefit-index">03</span>
                    <h3>Escalabilidade</h3>
                    <p>Comece no FREE e migre para o PRO sem trocar de sistema.</p>
                </article>
                <article class="benefit-card">
                    <span class="benefit-index">04</span>
                    <h3>Experiência moderna</h3>
                    <p>QR Code, interface responsiva e painéis adequados à rotina do negócio.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="planos">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">Planos</span>
                <h2>Comece no FREE e evolua para o PRO.</h2>
                <p>
                    O modelo foi pensado para deixar pequenos estabelecimentos entrarem com baixo atrito
                    e permitir crescimento com limites maiores conforme a operação aumenta.
                </p>
            </div>

            <div class="plans-grid">
                <article class="plan-card">
                    <span class="plan-label">Plano FREE</span>
                    <h3>Para começar a digitalizar a operação</h3>
                    <div class="plan-price">
                        <strong>R$ 0</strong>
                        <span>/mês</span>
                    </div>
                    <p>Entrada simples para testar o fluxo e organizar o atendimento.</p>
                    <ul class="plan-list">
                        <li>Até 10 mesas cadastradas</li>
                        <li>Até 100 pedidos por mês</li>
                        <li>Fluxo de atendimento, cozinha e caixa</li>
                        <li>Base ideal para pequenos estabelecimentos</li>
                    </ul>
                    <div class="plan-actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($linkWhatsappFree, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Quero o FREE</a>
                    </div>
                </article>

                <article class="plan-card pro">
                    <span class="plan-label">Plano PRO</span>
                    <h3>Mais capacidade para estabelecimentos em crescimento</h3>
                    <div class="plan-price">
                        <strong>R$ 129,90</strong>
                        <span>/mês</span>
                    </div>
                    <p>Operação ampliada para suportar mais mesas e maior volume mensal.</p>
                    <ul class="plan-list">
                        <li>Até 30 mesas cadastradas</li>
                        <li>Até 1.000 pedidos por mês</li>
                        <li>Estrutura pronta para operação mais intensa</li>
                        <li>Upgrade direto, sem trocar de sistema</li>
                    </ul>
                    <div class="plan-actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($linkWhatsappPro, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Quero o PRO</a>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($linkWhatsappDemo, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Falar com a Bacuri</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="publico">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">Para quem é</span>
                <h2>Feita para negócios que precisam vender com mais organização.</h2>
                <p>
                    A landing posiciona a Comandaria Express como uma solução prática para operações alimentícias
                    que precisam integrar atendimento, produção e fechamento.
                </p>
            </div>

            <div class="audience-grid">
                <article class="audience-card">
                    <span class="audience-tag">Bares</span>
                    <h3>Mesas, comandas e caixa sem desencontro</h3>
                    <p>Fluxo ideal para controlar pedidos por mesa, adicionais e contas em aberto.</p>
                </article>
                <article class="audience-card">
                    <span class="audience-tag">Lanchonetes</span>
                    <h3>Mais rapidez no balcão e na cozinha</h3>
                    <p>Pedidos bem organizados, interface objetiva e fechamento mais simples.</p>
                </article>
                <article class="audience-card">
                    <span class="audience-tag">Restaurantes</span>
                    <h3>Operação integrada do início ao pagamento</h3>
                    <p>Atendimento, produção e caixa trabalhando com a mesma base de informação.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-head">
                <span class="section-kicker">Valor percebido</span>
                <h2>Quatro motivos para apresentar a Comandaria como produto SaaS.</h2>
            </div>

            <div class="values-grid">
                <article class="value-card">
                    <span class="value-number">01</span>
                    <h3>Acesso inicial gratuito</h3>
                    <p>Reduz a barreira de entrada e facilita a adoção por pequenos negócios.</p>
                </article>
                <article class="value-card">
                    <span class="value-number">02</span>
                    <h3>Up-sell natural</h3>
                    <p>O PRO aparece como evolução lógica quando a operação ganhar escala.</p>
                </article>
                <article class="value-card">
                    <span class="value-number">03</span>
                    <h3>Mensagem comercial clara</h3>
                    <p>O visitante entende rapidamente o que o sistema resolve e para quem serve.</p>
                </article>
                <article class="value-card">
                    <span class="value-number">04</span>
                    <h3>Identidade Bacuri</h3>
                    <p>Visual profissional, escuro, responsivo e alinhado ao ecossistema dos produtos.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="cta-box">
                <div>
                    <h2>Comece a organizar sua operação com a Comandaria Express.</h2>
                    <p>
                        Entre pelo plano FREE, conheça o fluxo na prática e faça upgrade para o PRO
                        quando precisar de mais capacidade.
                    </p>
                </div>

                <div class="cta-actions">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($linkWhatsappFree, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Começar grátis</a>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($linkWhatsappPro, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Conhecer o PRO</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="container footer-row">
        <div><strong>Comandaria Express</strong> — Pedidos, mesas, cozinha e caixa em um só sistema.</div>
        <div>© <?= htmlspecialchars((string)$anoAtual, ENT_QUOTES, 'UTF-8') ?> Bacuri Digital. Todos os direitos reservados.</div>
    </div>
</footer>

<script>
    const navbar = document.getElementById('navbar');
    const mobileToggle = document.getElementById('mobileToggle');

    mobileToggle?.addEventListener('click', () => {
        const aberto = navbar.classList.toggle('open');
        mobileToggle.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        mobileToggle.textContent = aberto ? '×' : '☰';
    });

    document.querySelectorAll('.menu a').forEach(link => {
        link.addEventListener('click', () => {
            navbar.classList.remove('open');
            mobileToggle?.setAttribute('aria-expanded', 'false');
            if (mobileToggle) mobileToggle.textContent = '☰';
        });
    });
</script>
</body>
</html>
