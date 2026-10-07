<?php
// Pagrindinis puslapis: grupių pasirinkimas viršuje, apie klubą, kontaktai, registracija
require __DIR__ . '/app/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="VšĮ Karate Ateitis — tradicinio karate-do klubas Vilniuje. Treniruotės vaikams, jaunimui ir suaugusiems: kainos, tvarkaraščiai, vietos ir registracija. Pirmos dvi treniruotės nemokamos.">
<title>Karateka — VšĮ Karate Ateitis</title>
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
<link rel="icon" type="image/png" sizes="192x192" href="favicon-192.png?v=2">
<link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
<meta name="theme-color" content="#811517">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/header.css')) ?>">
<script>document.documentElement.classList.add('js');</script>
<style>
  :root{
    --ink:#17140F;
    --ink-deep:#120E0A;
    --muted:#4A453A;
    --cream:#F7F4EC;
    --accent:#2F6B5A;
    --accent-dark:#204B3F;
    --radius:16px;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  html{scroll-behavior:smooth;}
  body{
    font-family:'Inter', sans-serif;
    color:var(--ink);
    line-height:1.65;
    -webkit-font-smoothing:antialiased;
    overflow-x:hidden;
    /* one smooth, continuous gradient for the whole page - no patchwork, no seams */
    background:
      radial-gradient(ellipse 70% 40% at 18% 8%,  rgba(200,140,180,0.35), transparent 60%),
      radial-gradient(ellipse 70% 40% at 82% 22%, rgba(140,170,220,0.3), transparent 60%),
      radial-gradient(ellipse 70% 40% at 15% 38%, rgba(200,140,180,0.28), transparent 60%),
      radial-gradient(ellipse 70% 40% at 85% 52%, rgba(150,180,225,0.3), transparent 60%),
      radial-gradient(ellipse 70% 40% at 20% 68%, rgba(210,150,185,0.26), transparent 60%),
      radial-gradient(ellipse 70% 40% at 80% 82%, rgba(140,170,220,0.28), transparent 60%),
      radial-gradient(ellipse 80% 35% at 50% 96%, rgba(180,150,200,0.3), transparent 60%),
      #EDE9F2;
  }
  h1,h2,h3,h4{
    font-family:'Space Grotesk', sans-serif;
    font-weight:700;
    line-height:1.1;
    letter-spacing:-0.01em;
  }
  a{color:inherit; text-decoration:none;}
  img{max-width:100%; display:block;}
  .wrap{max-width:1120px; margin:0 auto; padding:0 32px; position:relative; z-index:2;}
  section{padding:100px 0; position:relative; z-index:2; scroll-margin-top:24px;}   /* sekcijos viršus pasislepia po meniu - nesimato ankstesnės sekcijos krašto, pavadinimas lieka matomas */
  .eyebrow{
    font-family:'JetBrains Mono', monospace; font-size:0.72rem; letter-spacing:0.16em;
    text-transform:uppercase; color:var(--accent); font-weight:700;
    display:inline-flex; align-items:center; gap:10px; margin-bottom:16px;
  }
  .eyebrow::before{content:""; width:24px; height:2px; background:var(--accent);}
  .section-head{max-width:640px; margin-bottom:44px;}
  .section-head h2{font-size:clamp(1.9rem,3.6vw,2.7rem);}
  .section-head h2.styled{
    display:inline-block; position:relative; padding-bottom:14px;
    background:linear-gradient(90deg, var(--ink) 0%, #7860A0 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent; -webkit-text-fill-color:transparent;
  }
  .section-head h2.styled::after{
    content:""; position:absolute; left:0; bottom:0; height:4px; width:64px;
    border-radius:4px;
    background:linear-gradient(90deg, #A85C7E, #5C68A0);
  }
  h2.styled-dark{
    display:inline-block; position:relative; padding-bottom:14px;
    background:linear-gradient(90deg, #F7F4EC 0%, #D9698C 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }
  h2.styled-dark::after{
    content:""; position:absolute; left:0; bottom:0; height:4px; width:64px;
    border-radius:4px;
    background:linear-gradient(90deg, #A85C7E, #5C68A0);
  }
  .section-head p{color:var(--muted); margin-top:14px; font-size:1.05rem;}

  .panel{
    background:rgba(255,255,255,0.62);
    backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
    border:1px solid rgba(255,255,255,0.55);
    border-radius:var(--radius);
    box-shadow:0 20px 44px rgba(23,20,15,0.10);
  }
  .panel-dark{
    background:rgba(20,16,12,0.55);
    backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:var(--radius);
    color:#F7F4EC;
  }
  .panel-dark p{color:#D8D2C2;}

  /* wavy full-width divider between sections - fixes empty ultra-wide sides */
  .wave-divider{width:100%; line-height:0; position:relative; z-index:2; margin-top:-1px;}
  .wave-divider svg{width:100%; height:60px; display:block;}

  /* Atsiradimas slenkant: elementas tolygiai išnyra ir lieka. .js - kad be JavaScript turinys liktų matomas. */
  .js .reveal{opacity:0; transform:translateY(18px); transition:opacity 0.9s ease, transform 0.9s cubic-bezier(0.2,0.7,0.2,1);}
  .js .reveal.in-view{opacity:1; transform:none;}


  /* ABOUT CLUB */
  .about-grid{display:flex; align-items:stretch; gap:48px;}   /* nuotrauka tokio pat aukščio kaip tekstas - be tuščių vietų */
  .about-grid > *{flex:1; min-width:0;}
  .about-left{display:flex; flex-direction:column; flex:0.85;}
  .about-left .panel{display:flex; flex-direction:column; justify-content:center;}
  .about-photo{border-radius:var(--radius); overflow:hidden; box-shadow:0 20px 44px rgba(23,20,15,0.15); position:relative; flex:1.15; min-height:340px;}
  .about-photo .slide{position:absolute; inset:0; opacity:0; transition:opacity 1.2s ease;}
  .about-photo .slide.active{opacity:1;}
  .about-photo img{width:100%; height:100%; object-fit:cover;}
  .about-panel{padding:40px 38px;}
  .about-panel p{margin-bottom:14px; color:var(--muted);}
  .about-quote{
    margin-top:18px; padding-top:16px; border-top:1px solid rgba(23,20,15,0.12);
    font-family:'Space Grotesk',sans-serif; font-size:1.02rem; color:var(--ink);
  }

  /* Istorija / Filosofija / Instruktoriai / Bendruomenė */
  .about-sub{margin-top:56px;}
  .about-sub-row{display:flex; gap:48px; align-items:flex-start;}
  .about-sub-col{flex:1; min-width:0;}
  @media(min-width:1500px){
    #apie .wrap{max-width:1520px;}
  }
  @media(min-width:1200px){
    html{font-size:18.4px;}
  }
  .about-sub-head{display:flex; align-items:baseline; gap:14px; margin-bottom:22px;}
  .about-sub-row .about-sub-head, .about-sub-head-center{justify-content:center; text-align:center;}
  .about-sub-head .num{font-family:'JetBrains Mono',monospace; font-size:0.78rem; color:var(--accent); font-weight:700;}
  .about-sub-head h3{
    font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:1.5rem; letter-spacing:-0.01em;
    background:linear-gradient(90deg, var(--ink) 0%, #A85C7E 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent; -webkit-text-fill-color:transparent;
  }

  .history-panel{padding:32px 36px;}
  .history-panel p{color:var(--muted); font-size:0.98rem; margin-bottom:12px;}
  .history-panel p:last-child{margin-bottom:0;}
  .history-panel strong{color:var(--ink);}

  /* ŠAKNYS IR VERTYBĖS - istorija viduryje, po 3 vertybes iš šonų (kairės pusės - veidrodiškai, ženklas arčiau vidurio) */
  .roots-row{
    display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.75fr) minmax(0,1fr); gap:14px 22px; align-items:stretch;
    grid-template-areas:"v1 h v4" "v2 h v5" "v3 h v6";
  }
  /* Vertybės be dėžučių: ženklas + tekstas, tarp jų plona linija, išnykstanti į kraštą */
  .value-item{display:flex; align-items:center; gap:16px; padding:6px 4px; position:relative;}
  .value-item::after{content:""; position:absolute; left:0; right:0; bottom:-7px; height:1px; background:linear-gradient(90deg, rgba(23,20,15,0.14), transparent);}
  .value-item.l::after{background:linear-gradient(270deg, rgba(23,20,15,0.14), transparent);}
  .value-item:nth-child(4)::after, .value-item:nth-child(7)::after{display:none;}   /* paskutinės stulpelyje (v3, v6) */
  .value-item.l{flex-direction:row-reverse; text-align:right;}
  .value-item strong{display:block; font-size:0.92rem; font-weight:600; line-height:1.3; color:var(--ink);}
  .value-translation{display:block; font-family:'JetBrains Mono',monospace; font-size:0.62rem; font-style:italic; color:var(--muted); font-weight:500; margin-bottom:2px;}
  .value-icon{
    width:48px; height:48px; border-radius:13px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:1.25rem; color:#fff; font-weight:700;
    box-shadow:0 6px 14px rgba(23,20,15,0.16);
  }
  .value-icon.rose{background:linear-gradient(135deg,#A85C7E,#8C4468);}
  .value-icon.indigo{background:linear-gradient(135deg,#5C68A0,#3A4A88);}
  .value-icon.green{background:linear-gradient(135deg,var(--accent),var(--accent-dark));}
  .roots-history{grid-area:h; padding:34px 36px; display:flex; flex-direction:column; justify-content:center;}
  .roots-history .kicker{
    font-family:'JetBrains Mono',monospace; font-size:0.68rem; letter-spacing:0.12em;
    text-transform:uppercase; color:var(--accent); font-weight:700; margin-bottom:14px;
  }
  .roots-history p{color:var(--muted); font-size:0.96rem; margin-bottom:12px;}
  .roots-history p:last-child{margin-bottom:0;}
  .roots-history strong{color:var(--ink);}

  .instructor-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:14px;}
  .instructors-row{display:flex; gap:32px; align-items:stretch;}
  .instructors-intro{flex:0 0 clamp(280px,27%,380px); padding:30px 28px; display:flex; flex-direction:column; justify-content:center;}
  .instructors-intro p{color:var(--muted); font-size:0.95rem;}
  .instructors-row .instructor-grid{flex:1; min-width:0; grid-template-columns:repeat(3,1fr); grid-auto-rows:1fr;}
  .instructor-card{padding:24px 16px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;}
  .instructor-card.lead{border:1.5px solid rgba(168,92,126,0.5);}
  .instructor-card .avatar{
    width:52px; height:52px; margin:0 auto 12px; border-radius:50%;
    background:linear-gradient(135deg, #A85C7E, #5C68A0);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:1.1rem;
  }
  .instructor-card .name{font-size:0.92rem; font-weight:700; color:var(--ink); margin-bottom:4px;}
  .instructor-card .rank{font-family:'JetBrains Mono',monospace; font-size:0.7rem; color:var(--accent); font-weight:600;}

  .community-banner{
    padding:36px 40px; text-align:center;
    font-family:'Space Grotesk',sans-serif; font-size:1.15rem; color:var(--ink);
  }
  .community-banner span{color:var(--accent);}
  .about-locations{margin-top:18px; padding-top:16px; border-top:1px solid rgba(23,20,15,0.12);}
  .about-locations .kicker{font-family:'JetBrains Mono',monospace; font-size:0.68rem; letter-spacing:0.1em; text-transform:uppercase; color:var(--accent); font-weight:700; margin-bottom:8px;}
  .about-locations p{color:var(--ink); font-size:0.95rem; margin-bottom:0;}

  /* GROUP CAROUSEL - full-bleed horizontal slides, photo blends into a dark overlay */
  /* Karuselės tekstas fiksuoto dydžio (em nuo 16px), kad dideliuose ekranuose neišaugtų ir tilptų į ekraną */
  .group-scroll{font-size:16px;}
  .group-content .eyebrow{font-size:0.72em;}
  .group-content .btn{font-size:0.9em; padding:12px 24px;}
  .group-content .schedule-line{font-size:0.88em;}
  /* group scroll window - one viewport-height window you scroll LEFT/RIGHT through (mouse/trackpad/touch), black photo backgrounds */
  .group-scroll{
    position:relative; z-index:2;
    width:100%; margin-left:0;
    height:auto; min-height:0;   /* aukštis pagal turinį, kad apačioje matytųsi fonas */
    display:flex;
    overflow-x:auto; overflow-y:hidden;
    scroll-snap-type:x mandatory;
    scroll-behavior:smooth;
    -webkit-overflow-scrolling:touch;
  }
  .group-scroll::-webkit-scrollbar{display:none;}
  .group-section{
    flex:0 0 100%; width:100%; height:auto; min-height:0; position:relative;
    scroll-snap-align:start;
    display:flex; align-items:stretch;
    background:#150E12;
    overflow:hidden;
  }
  .group-photo-panel{position:relative; width:40%; flex-shrink:0; align-self:stretch; overflow:hidden;}
  .group-photo-panel img{position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:block;}
  .group-photo-panel::after{
    content:""; position:absolute; inset:0;
    background:linear-gradient(90deg, transparent 48%, #150E12 96%);
  }
  .group-text-panel{flex:1; display:flex; align-items:stretch; min-width:0;}   /* turinys nuo viršaus - „VAIKAMS / JAUNIMUI / SUAUGUSIEMS“ visose skaidrėse tame pačiame aukštyje */
  .group-content{position:relative; z-index:2; padding:62px 84px 24px 3vw; max-width:none; color:#F7F4EC; width:100%; display:flex; flex-direction:column;}
  .group-content .eyebrow{color:#F7F4EC; margin-bottom:12px;}
  .group-content .eyebrow::before{background:#F7F4EC;}
  .group-content h2{font-size:clamp(1.35em,1.9vw,1.7em); color:#F7F4EC; margin-bottom:10px;}
  .group-content > p{color:#D8D2C2; font-size:0.95em; margin-bottom:14px; max-width:560px;}
  .group-facts{display:grid; grid-template-columns:1fr 1fr; gap:18px 26px; border-top:1px solid rgba(247,244,236,0.25); padding-top:16px; margin-bottom:16px;}
  .group-cta{align-self:flex-start; margin-top:auto;}   /* mygtukas visose skaidrėse apačioje, tame pačiame aukštyje */
  #vaikams .group-facts{grid-template-columns:minmax(150px,0.55fr) 2fr;}
  #vaikams .group-facts .full{grid-column:auto;}
  #jaunimui .group-facts, #suaugusiems .group-facts{grid-template-columns:auto 1.6fr 1fr;}

  #jaunimui .group-facts .full, #suaugusiems .group-facts .full{grid-column:auto;}
  .group-facts .full{grid-column:1 / -1;}
  .group-facts .kicker{font-family:'JetBrains Mono',monospace; font-size:0.66em; letter-spacing:0.1em; text-transform:uppercase; color:#F7F4EC; font-weight:700; margin-bottom:10px; opacity:0.8;}
  .group-facts p{font-size:0.88em; color:#D8D2C2;}
  .loc-list{list-style:none; display:grid; gap:6px; margin-bottom:6px;}
  .loc-list li{font-size:0.82em; color:#D8D2C2; padding-left:14px; text-indent:-14px;}
  .loc-list li::before{content:"● "; color:#E8749E; font-size:0.55em; vertical-align:middle;}
  .loc-group{padding:6px 0;}
  .loc-scroll{max-height:142px; overflow-y:auto; padding-right:6px;}
  /* Vietų sąrašas fiksuoto aukščio: išskleidus slenkama viduje, o visas langas nedidėja */
  .schedule-picker{height:clamp(180px,24vh,250px);}
  .schedule-picker > *{min-height:0; overflow-y:auto;}
  .schedule-picker .picker-locations{max-height:none;}
  .loc-scroll::-webkit-scrollbar{width:4px;}
  .loc-scroll::-webkit-scrollbar-thumb{background:rgba(247,244,236,0.3); border-radius:4px;}
  .loc-scroll::-webkit-scrollbar-track{background:transparent;}
  .loc-group summary{
    cursor:pointer; font-size:0.85em; font-weight:600; color:#F7F4EC;
    display:flex; justify-content:space-between; align-items:center; list-style:none;
  }
  .loc-group summary::-webkit-details-marker{display:none;}
  .loc-group summary::after{content:"+"; color:#D9698C; font-weight:700;}
  .loc-group[open] summary::after{content:"–";}
  .loc-group ul{list-style:none; margin-top:6px; display:grid; gap:4px;}
  .loc-group ul li{font-size:0.78em; color:#D8D2C2; padding-left:14px; text-indent:-14px;}
  .loc-time{display:block; text-indent:0; font-size:0.92em; color:#B9B2A0; margin-top:2px; line-height:1.4;}

  /* interactive schedule picker (vaikams) */
  .schedule-picker{display:grid; grid-template-columns:minmax(0,1.1fr) minmax(0,1fr); gap:16px; align-items:stretch;}
  .picker-locations ul li{cursor:pointer; border-radius:6px; padding:5px 8px 5px 16px; text-indent:-16px; margin-left:-8px; line-height:1.35; hyphens:auto; word-wrap:break-word; overflow-wrap:break-word; transition:background 0.15s ease, color 0.15s ease;}
  .picker-locations ul li:hover{background:rgba(247,244,236,0.08);}
  .picker-locations ul li.active{background:rgba(233,116,158,0.16); color:#F7F4EC;}
  .picker-locations ul li.active::before{color:#F7F4EC;}

  /* consistent day + time layout, used everywhere a schedule is shown */
  .schedule-line{display:flex; flex-wrap:wrap; justify-content:space-between; align-items:baseline; column-gap:14px; row-gap:2px; padding:8px 0; border-bottom:1px solid rgba(247,244,236,0.1);}
  .schedule-line:last-child{border-bottom:none;}
  .schedule-day{color:#D8D2C2; font-size:0.85em; line-height:1.4; flex:1 1 130px; min-width:0; overflow-wrap:break-word;}
  .schedule-time{color:#F7F4EC; font-weight:700; font-size:0.8em; font-family:'JetBrains Mono',monospace; letter-spacing:-0.02em; white-space:nowrap; flex:0 0 auto;}
  .picker-schedule{
    background:rgba(247,244,236,0.06); border:1px solid rgba(247,244,236,0.12); border-radius:12px;
    padding:16px 16px; min-height:142px; min-width:0; overflow-wrap:break-word; display:flex; flex-direction:column; justify-content:flex-start;
  }
  .picker-schedule .kicker{margin-bottom:10px;}
  .picker-schedule .schedule-line{display:block; padding:7px 0;}
  .picker-schedule .schedule-day{display:block;}
  .picker-schedule .schedule-time{display:block; margin-top:2px; font-size:0.82em;}

  .picker-schedule .schedule-placeholder{font-size:0.85em; color:#8F8874; font-style:italic;}
  .picker-schedule .schedule-name{font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:0.92em; line-height:1.3; color:#F7F4EC; margin-bottom:8px; overflow-wrap:break-word; hyphens:auto;}
  .loc-group ul li::before{content:"● "; color:#E8749E; font-size:0.55em; vertical-align:middle;}
  .group-facts ul{list-style:none; display:grid; gap:6px;}
  .group-facts ul li{font-size:0.88em; color:#D8D2C2;}
  .group-facts ul li::before{content:"— ";}
  .group-price{font-family:'Space Grotesk',sans-serif; font-size:1.6em; color:#F7F4EC; display:flex; flex-direction:column; gap:0;}
  .group-price .price-condition{display:block; text-align:left; margin-top:-2px;}
  .group-price .price-condition{font-family:'Inter',sans-serif; font-size:0.44em;   /* = 0.7 pagrindinio dydžio */ font-weight:500; color:#F7F4EC;}
  .group-price-alt{font-family:'Inter',sans-serif; font-size:0.78em; color:#B9B2A0; margin-top:3px;}

  /* left/right controls + bottom dots for the horizontal scroll window */
  .group-arrow{
    position:absolute; top:50%; transform:translateY(-50%); z-index:20;
    width:48px; height:48px; border-radius:50%;
    background:rgba(20,16,12,0.45); border:1px solid rgba(247,244,236,0.4);
    color:#F7F4EC; font-size:1.2em; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    backdrop-filter:blur(8px); box-shadow:0 4px 14px rgba(0,0,0,0.25);
    transition:background 0.2s ease;
  }
  .group-arrow:hover{background:rgba(47,107,90,0.85); border-color:rgba(47,107,90,0.9);}
  .group-arrow.prev{left:24px;}
  .group-arrow.next{right:24px;}
  .group-dots{
    position:absolute; bottom:26px; left:50%; transform:translateX(-50%); z-index:20;
    display:flex; gap:10px;
  }
  .group-dots button{
    width:9px; height:9px; border-radius:50%; border:none; padding:0;
    background:rgba(247,244,236,0.35); cursor:pointer; transition:background 0.2s ease, transform 0.2s ease;
  }
  .group-dots button.active{background:#F7F4EC; transform:scale(1.3);}
  .section-tag{
    position:absolute; top:26px; left:6vw; z-index:20;
    font-family:'JetBrains Mono',monospace; font-size:0.72rem; letter-spacing:0.16em;
    text-transform:uppercase; color:#F7F4EC; font-weight:700;
    background:rgba(23,20,15,0.35); backdrop-filter:blur(6px);
    padding:8px 14px; border-radius:20px; border:1px solid rgba(247,244,236,0.25);
  }

  .btn{display:inline-block; padding:15px 28px; font-weight:700; font-size:0.9rem; border-radius:10px; transition:transform 0.15s ease, background 0.15s ease;}
  .btn-primary{background:var(--accent); color:#fff;}
  .btn-primary:hover{background:var(--accent-dark); transform:translateY(-2px);}

  /* CONTACT (info only) */
  .contact-panel{padding:40px 38px;}
  .contact-list{list-style:none; display:grid; gap:20px;}
  .contact-list li{display:flex; gap:16px;}
  .contact-list .mark{width:36px; height:36px; background:var(--accent); flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:0.85rem; font-weight:800; border-radius:8px; color:#fff;}
  .contact-list strong{display:block; font-size:0.95rem;}
  .contact-list span{color:var(--muted); font-size:0.9rem;}

  /* REGISTRATION (form, light frosted panel matching the rest of the site) */
  .registration-grid{display:grid; grid-template-columns:0.9fr 1.1fr; gap:0;}
  .registration-panel{padding:44px 40px;}
  .registration-panel p{color:var(--muted);}
  form.contact-form{display:grid; gap:14px;}
  form.contact-form input, form.contact-form textarea, form.contact-form select{
    background:rgba(255,255,255,0.7); border:1px solid rgba(23,20,15,0.14); color:var(--ink);
    padding:14px 16px; font-family:'Inter',sans-serif; font-size:0.92rem; border-radius:8px;
    width:100%; appearance:none; -webkit-appearance:none;
  }
  form.contact-form select{
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8'><path d='M1 1l5 5 5-5' stroke='%235B5648' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>");
    background-repeat:no-repeat; background-position:right 16px center;
  }
  form.contact-form select:invalid, form.contact-form select.is-empty{color:#9C9484;}
  form.contact-form input:focus, form.contact-form textarea:focus, form.contact-form select:focus{outline:2px solid #2F6B5A; outline-offset:1px;}
  form.contact-form input::placeholder, form.contact-form textarea::placeholder{color:#9C9484;}
  .form-status{font-size:0.86rem; text-align:center; min-height:20px;}
  .form-status.ok{color:#2F6B5A; font-weight:600;}
  .form-status.err{color:#B5281F; font-weight:600;}

  footer{padding:44px 0 30px; position:relative; z-index:2;}
  .footer-grid{display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:24px;}
  .footer-grid .logo img{height:34px; filter:brightness(0) invert(1);}
  .footer-links{display:flex; gap:26px; flex-wrap:wrap; list-style:none;}
  .footer-links a{font-size:0.86rem; color:#C9C2AF;}
  .footer-links a:hover{color:#F7F4EC;}
  .footer-bottom{border-top:1px solid rgba(247,244,236,0.15); padding-top:18px; display:flex; justify-content:space-between; font-size:0.78rem; color:#9C9484; flex-wrap:wrap; gap:10px;}

  @media(max-width:1080px){
    .roots-row{grid-template-columns:1fr 1fr; grid-template-areas:"h h" "v1 v4" "v2 v5" "v3 v6";}
    .value-item.l{flex-direction:row; text-align:left;}
  }
  @media(max-width:560px){
    .roots-row{grid-template-columns:1fr; grid-template-areas:"h" "v1" "v2" "v3" "v4" "v5" "v6"; gap:10px;}
  }
  @media(max-width:860px){
    .roots-history{padding:28px 24px;}
    .about-grid{flex-direction:column;}
    .about-sub-row{flex-direction:column; gap:0;}
    .about-photo{min-height:360px; aspect-ratio:auto; flex:none; width:100%;}
    .instructors-row{flex-direction:column; gap:20px;}
    .instructors-intro{flex:none;}
    .instructor-grid, .instructors-row .instructor-grid{grid-template-columns:repeat(2,1fr);}
    .registration-grid{grid-template-columns:1fr;}
    .group-facts, #vaikams .group-facts, #jaunimui .group-facts, #suaugusiems .group-facts{grid-template-columns:1fr; gap:18px;}
    .group-facts > *{grid-row:auto !important; grid-column:auto !important;}
    .schedule-picker{grid-template-columns:1fr; gap:14px; height:auto;}
    .schedule-picker .picker-locations{height:220px;}
    .schedule-picker > .picker-schedule{overflow:visible;}
    .picker-schedule{min-height:auto;}
    .group-content{max-width:100%; padding:0 24px 70px;}
    .group-arrow{width:34px; height:34px; font-size:0.95rem; top:auto; bottom:14px; transform:none;}
    .group-arrow.prev{left:10px; right:auto;}
    .group-arrow.next{right:10px; left:auto;}
    .group-scroll{height:auto; min-height:0;}
    .group-section{height:auto; min-height:680px; padding:76px 0 40px; display:block; background:none;}
    .group-photo-panel{position:absolute; inset:0; width:100%; height:100%;}
    .group-photo-panel::after{background:linear-gradient(90deg, rgba(10,8,6,0.92) 0%, rgba(10,8,6,0.72) 34%, rgba(10,8,6,0.25) 68%, rgba(10,8,6,0.1) 100%);}
    .group-text-panel{position:relative; z-index:2; width:100%; display:block;}
    .about-panel, .contact-panel, .registration-panel{padding:30px 24px;}
  }
  @media (prefers-reduced-motion: reduce){
    html{scroll-behavior:auto;}
    *{transition:none !important; animation:none !important;}
    .js .reveal{opacity:1; transform:none;}
  }
  /* GRUPIŲ PASIRINKIMAS - pavadinimas virš karuselės, mygtukai pačioje karuselėje */
  .carousel-wrap{position:relative; max-width:1200px; margin:0 32px; border-radius:22px; overflow:hidden; box-shadow:0 24px 50px rgba(23,20,15,0.18);}
  @media(min-width:1264px){ .carousel-wrap{margin:0 auto;} }
  @media(max-width:860px){ .carousel-wrap{margin:0 12px; border-radius:16px;} }
  .chooser{text-align:center; padding:12px 24px 14px; position:relative; z-index:2;}
  .chooser-title{
    display:inline-block; position:relative; padding-bottom:12px;
    font-size:clamp(1.5rem,3vw,2.2rem);
    background:linear-gradient(90deg, var(--ink) 0%, #7860A0 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent; -webkit-text-fill-color:transparent;
  }
  .chooser-title::after{
    content:""; position:absolute; left:50%; transform:translateX(-50%); bottom:0; height:4px; width:64px; border-radius:4px;
    background:linear-gradient(90deg, #A85C7E, #5C68A0);
  }
  .chooser-buttons{position:absolute; top:18px; left:50%; transform:translateX(-50%); z-index:20; display:flex; gap:8px;}
  .chooser-buttons button{
    font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:0.82rem; color:#F7F4EC; cursor:pointer; white-space:nowrap;
    padding:8px 16px; border-radius:20px;
    background:rgba(20,16,12,0.28); border:1px solid rgba(247,244,236,0.35);
    backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
    transition:background 0.2s ease, border-color 0.2s ease;
  }
  .chooser-buttons button:hover{background:rgba(247,244,236,0.16);}
  .chooser-buttons button.active{background:rgba(168,92,126,0.75); border-color:rgba(217,105,140,0.8);}
  @media(max-width:860px){
    .chooser{padding:22px 16px 16px;}
    .chooser-buttons{gap:6px;}
    .chooser-buttons button{font-size:0.76rem; padding:7px 11px;}
  }
  .first-screen{min-height:calc(100vh - 73px); min-height:calc(100svh - 73px); padding-bottom:24px;}   /* 73px - viršutinė juosta */
  #apie{padding-bottom:40px;}
  #kontaktai{padding:40px 0;}
  #kontaktai .section-head{margin-bottom:24px;}
  #registracija{padding-top:40px;}
  /* ARTĖJANTYS RENGINIAI */
  /* Tokio pat pločio kaip grupių langas; antraštė ir „Visi renginiai“ vienoje eilutėje virš kortelių */
  .home-events-section{padding:20px 0 0;}
  .events-wrap{max-width:1200px; margin:0 32px; position:relative; z-index:2;}
  @media(min-width:1264px){ .events-wrap{margin:0 auto;} }
  .events-head{display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:12px;}
  .events-head h2{
    margin:0; font-size:1.3rem;
    background:linear-gradient(90deg, var(--ink) 0%, #7860A0 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent; -webkit-text-fill-color:transparent;
  }
  .events-all{
    font-size:0.8rem; font-weight:700; white-space:nowrap; padding:8px 16px; border-radius:20px;
    background:rgba(255,255,255,0.62); border:1px solid rgba(255,255,255,0.7); box-shadow:0 6px 16px rgba(23,20,15,0.08);
    transition:background 0.15s ease;
  }
  .events-all:hover{background:#fff;}
  .events-grid{display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:16px;}
  @media(max-width:860px){
    .events-wrap{margin:0 12px;}
    .events-grid{grid-template-columns:1fr; gap:10px;}
  }
  .home-event{display:flex; gap:14px; padding:14px 16px; align-items:center; color:var(--ink); transition:box-shadow 0.15s ease;}
  .home-event:hover{box-shadow:0 20px 44px rgba(23,20,15,0.16);}
  .he-date{flex-shrink:0; width:54px; text-align:center; border-radius:10px; background:var(--ink); color:var(--cream); padding:7px 4px; line-height:1.1;}
  .he-date .d{display:block; font-family:'Space Grotesk',sans-serif; font-size:1.3rem; font-weight:700;}
  .he-date .m{font-family:'JetBrains Mono',monospace; font-size:0.6rem; text-transform:uppercase; letter-spacing:0.08em;}
  .he-body{flex:1; min-width:0;}
  .he-type{display:inline-block; font-size:0.66rem; font-weight:700; padding:2px 9px; border-radius:20px; background:rgba(168,92,126,0.14); color:#7A3558;}
  .home-event h3{font-size:0.95rem; line-height:1.25; margin:4px 0 2px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;}
  .he-meta{font-size:0.76rem; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
</style>
</head>
<body>

<?php site_header(true); ?>

<!-- Pirmas ekranas: grupės + renginiai. Užpildo visą ekraną, kad apačioje nesimatytų kitos dalies kraštelio -->
<div class="first-screen">
<section style="padding:0; position:relative;" id="grupes">
  <div class="chooser"><h1 class="chooser-title">Kam ieškote treniruočių?</h1></div>
  <div class="carousel-wrap">
  <div class="group-scroll" id="groupScroll">

    <div class="group-section" id="vaikams">
      <div class="group-photo-panel"><img src="img/vaikai.jpg" alt="Vaikų treniruotė" style="object-position:50% 25%;"></div>
      <div class="group-text-panel"><div class="group-content">
        <div class="eyebrow">VAIKAMS</div>
        <h2>Žaismingas įvadas į discipliną ir pagarbą</h2>
        <p>Vaikų ir priešmokyklinukų grupė, kur mokomasi per žaidimą — pagrindinės technikos, koordinacija ir pirmieji žingsniai karate kelyje.</p>
        <div class="group-facts">
          <div>
            <div class="kicker">Kainos</div>
            <?= render_price('vaikai') ?>
          </div>
          <div class="full">
            <div class="kicker">Kur treniruojamės</div>
            <div class="schedule-picker">
              <div class="picker-locations loc-scroll loc-accordion">
                <?= render_kids_locations() ?>
              </div>
              <div class="picker-schedule" id="vaikamsSchedulePanel">
                <div class="kicker">Tvarkaraštis</div>
                <p class="schedule-placeholder">← Pasirinkite vietą kairėje</p>
              </div>
            </div>
          </div>
        </div>
        <a href="#registracija" class="btn btn-primary group-cta">Registruotis</a>
      </div></div>
    </div>

    <div class="group-section" id="jaunimui">
      <div class="group-photo-panel"><img src="img/jaunimas.jpg" alt="Jaunimo treniruotė" style="object-position:62% 20%;"></div>
      <div class="group-text-panel"><div class="group-content">
        <div class="eyebrow">JAUNIMUI</div>
        <h2>Tempas, technika ir pasiruošimas varžyboms</h2>
        <p>Paaugliams skirtos treniruotės, kuriose derinama technika, fizinis pasirengimas ir noras siekti rezultatų — nuo pirmų kata iki varžybų.</p>
        <div class="group-facts">
          <div>
            <div class="kicker">Kainos</div>
            <?= render_price('jaunimas') ?>
          </div>
          <div>
            <div class="kicker">Tvarkaraštis</div>
            <?= render_category_schedule('jaunimas') ?>
          </div>
          <div class="full">
            <div class="kicker">Kur treniruojamės</div>
            <?= render_category_locations('jaunimas') ?>
          </div>
        </div>
        <a href="#registracija" class="btn btn-primary group-cta">Registruotis</a>
      </div></div>
    </div>

    <div class="group-section" id="suaugusiems">
      <div class="group-photo-panel"><img src="img/suaugusieji.jpg" alt="Karatistas ant varžybų tatamio" style="object-position:24% 30%;"></div>
      <div class="group-text-panel"><div class="group-content">
        <div class="eyebrow">SUAUGUSIEMS</div>
        <h2>Jėga, forma ir aiški galva po darbo dienos</h2>
        <p>Treniruotės suaugusiems, pritaikytos tiek pradedantiesiems, tiek turintiems patirties. Realus fizinis krūvis kartu su savigynos pagrindais.</p>
        <div class="group-facts">
          <div>
            <div class="kicker">Kainos</div>
            <?= render_price('suauge') ?>
          </div>
          <div>
            <div class="kicker">Tvarkaraštis</div>
            <?= render_category_schedule('suauge') ?>
          </div>
          <div class="full">
            <div class="kicker">Kur treniruojamės</div>
            <?= render_category_locations('suauge') ?>
          </div>
        </div>
        <a href="#registracija" class="btn btn-primary group-cta">Registruotis</a>
      </div></div>
    </div>

  </div>

  <button class="group-arrow prev" id="groupPrev" aria-label="Ankstesnė grupė">‹</button>
  <button class="group-arrow next" id="groupNext" aria-label="Kita grupė">›</button>
  <div class="chooser-buttons" id="groupTabs" role="tablist">
        <button type="button" data-i="0" class="active" role="tab">Vaikams</button>
        <button type="button" data-i="1" role="tab">Jaunimui</button>
        <button type="button" data-i="2" role="tab">Suaugusiems</button>
      </div>
  </div>
</section>

<?php $homeEvents = public_upcoming_events(3); if ($homeEvents): ?>
<section id="renginiai" class="home-events-section">
  <div class="events-wrap">
    <div class="events-head">
      <h2>Artėjantys renginiai</h2>
      <a class="events-all" href="renginiai.php">Visi renginiai →</a>
    </div>
    <div class="events-grid">
      <?php foreach ($homeEvents as $ev): $ts = strtotime($ev['starts_on']); ?>
        <a class="panel home-event reveal" href="renginiai.php#e<?= (int) $ev['id'] ?>">
          <div class="he-date"><span class="d"><?= date('j', $ts) ?></span><span class="m"><?= e(mb_substr(LT_MONTHS_GEN[(int) date('n', $ts)], 0, 3)) ?></span></div>
          <div class="he-body">
            <span class="he-type"><?= e(EVENT_TYPES[$ev['type']]) ?></span>
            <h3><?= e($ev['title']) ?></h3>
            <div class="he-meta"><?= e(fmt_date($ev['starts_on'])) ?><?= $ev['ends_on'] && $ev['ends_on'] !== $ev['starts_on'] ? ' – ' . e(fmt_date($ev['ends_on'])) : '' ?><?= $ev['start_time'] ? ', ' . e(fmt_time($ev['start_time'])) : '' ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
</div>

<section id="apie" style="position:relative;">
  <div class="wrap">
    <div class="about-grid">
      <div class="about-left reveal">
        <div class="section-head" style="margin-bottom:20px;">
          <h2 class="styled">Klubas, statantis charakterį</h2>
        </div>
        <div class="panel about-panel">
          <p>„Karateka" — tradicinio karate-do klubas Vilniuje, kuriame savo kelią gali pradėti ir tęsti vaikai, jaunimas ir suaugusieji.</p>
          <p>Mus vienija ne tik technikos tobulinimas, bet ir noras augti kaip asmenybėms — stiprinti kūną, ugdyti charakterį ir mokytis pagarbos sau bei aplinkiniams.</p>
          <div class="about-quote">„Tikras karate meistriškumas prasideda nuo darbo su savimi — kūno, proto ir dvasios darnos."</div>
        </div>
      </div>
      <div class="about-photo reveal" id="aboutSlider">
        <div class="slide active"><img src="img/apie-1.jpg" alt="Karateka komanda su medaliais"></div>
        <div class="slide"><img src="img/apie-2.jpg" alt="Sportininkės išeina į varžybų tatamį"></div>
        <div class="slide"><img src="img/apie-3.jpg" alt="Apkabinimas po kovos"></div>
        <div class="slide"><img src="img/apie-4.jpg" alt="Vaikų komanda su trofėjais"></div>
        <div class="slide"><img src="img/apie-5.jpg" alt="Kata varžybose"></div>
      </div>
    </div>

    <div class="about-sub">
      <div class="about-sub-head about-sub-head-center"><h3>Šaknys ir vertybės</h3></div>

      <div class="roots-row">
        <div class="panel roots-history reveal">
          <div class="kicker">Mūsų istorija</div>
          <p>Klubo šaknys siekia <strong>2013 metus</strong>, kai treniravomės Tradicinio karate-do klubo „Sanrei" komandoje. Įgiję patirties ir subrendę savo idėjai, <strong>2017 metais</strong> įkūrėme savarankišką klubą „Karateka", kuriam vadovauja Denis Balinskis.</p>
          <p>Denis savo karate kelią pradėjo būdamas vos 5 metų — ir šią vaikystės svajonę įgyvendino, sukurdamas bendruomenę, kurioje šiandien sportuoja žmonės nuo 2 iki 54 metų.</p>
        </div>
        <div class="value-item l reveal" style="grid-area:v1;"><span class="value-icon rose">道</span><span><span class="value-translation">kelias</span><strong>Saviugda</strong></span></div>
        <div class="value-item l reveal" style="grid-area:v2;"><span class="value-icon indigo">力</span><span><span class="value-translation">jėga</span><strong>Charakterio ir valios stiprinimas</strong></span></div>
        <div class="value-item l reveal" style="grid-area:v3;"><span class="value-icon green">忍</span><span><span class="value-translation">kantrybė</span><strong>Savidisciplina</strong></span></div>
        <div class="value-item reveal" style="grid-area:v4;"><span class="value-icon rose">礼</span><span><span class="value-translation">pagarba</span><strong>Pagarba tradicijoms ir dojo etiketui</strong></span></div>
        <div class="value-item reveal" style="grid-area:v5;"><span class="value-icon indigo">守</span><span><span class="value-translation">apsauga</span><strong>Savigynos įgūdžiai</strong></span></div>
        <div class="value-item reveal" style="grid-area:v6;"><span class="value-icon green">和</span><span><span class="value-translation">harmonija</span><strong>Vidinė pusiausvyra</strong></span></div>
      </div>
    </div>

    <div class="about-sub">
      <div class="about-sub-head about-sub-head-center"><h3>Mūsų instruktoriai</h3></div>
      <div class="instructors-row">
        <div class="panel instructors-intro reveal"><p>Klubo instruktoriai nuolat gilina žinias, dalyvaudami stažuotėse Lietuvoje ir užsienyje — karate, fizioterapijos, psichologijos, sporto, medicinos, masažų, biomechanikos, sporto mitybos, kinesiologijos ir sporto pedagogikos srityse. Tai leidžia mums treniruotes kurti remiantis ne tik karate tradicijomis, bet ir šiuolaikiniais mokslo pasiekimais.</p></div>
        <div class="instructor-grid">
          <div class="panel instructor-card lead reveal">
            <div class="avatar">DB</div>
            <div class="name">Denis Balinskis</div>
            <div class="rank">3 Dan · klubo vadovas</div>
          </div>
          <div class="panel instructor-card reveal">
            <div class="avatar">LČ</div>
            <div class="name">Lukas Čiulkinas</div>
            <div class="rank">1 Dan</div>
          </div>
          <div class="panel instructor-card reveal">
            <div class="avatar">JK</div>
            <div class="name">Joris Kėrys</div>
            <div class="rank">1 Dan</div>
          </div>
          <div class="panel instructor-card reveal">
            <div class="avatar">EČ</div>
            <div class="name">Esmilė Česevičiūtė</div>
            <div class="rank">1 Dan</div>
          </div>
          <div class="panel instructor-card reveal">
            <div class="avatar">KK</div>
            <div class="name">Karolis Kiela</div>
            <div class="rank">1 Dan</div>
          </div>
          <div class="panel instructor-card reveal">
            <div class="avatar">GL</div>
            <div class="name">Giedrius Li Tang</div>
            <div class="rank">1 Dan</div>
          </div>
        </div>
      </div>
    </div>

    <div class="about-sub">
      <div class="panel community-banner reveal">
        „Karateka" — tai klubas, kuriame karate tampa <span>visos šeimos veikla</span>. Treniruotėse kartu sportuoja tėvai su vaikais — amžiaus riba mums nėra kliūtis, svarbiausia noras augti ir tobulėti.
      </div>
    </div>
  </div>
</section>



<section id="kontaktai" style="position:relative;">
  <div class="wrap">
    <div class="section-head reveal">
      <h2 class="styled">Kur mus rasti</h2>
    </div>
    <div class="panel contact-panel reveal">
      <ul class="contact-list">
        <li><span class="mark">V</span><div><strong>VšĮ Karate Ateitis</strong><span>Vilnius, Lietuva</span></div></li>
        <li><span class="mark">@</span><div><strong>El. paštas</strong><span>info@karateka.lt</span></div></li>
        <li><span class="mark">T</span><div><strong>Telefonas</strong><span>+370 601 44392</span></div></li>
        <li><span class="mark">#</span><div><strong>Įmonės kodas</strong><span>305105392</span></div></li>
        <li><span class="mark">$</span><div><strong>Sąskaita</strong><span>LT76 7300 0101 6008 0310</span></div></li>
      </ul>
    </div>
  </div>
</section>


<section class="contact" id="registracija" style="position:relative;">
  <div class="wrap">
    <div class="section-head reveal">
      <h2 class="styled">Registruokis</h2>
      <p>Užpildykite formą ir išbandykite pirmąją treniruotę nemokamai</p>
    </div>
    <div class="panel registration-grid reveal">
      <div class="registration-panel">
        <h3 style="font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:1.5rem; letter-spacing:-0.01em; margin-bottom:10px; background:linear-gradient(90deg, var(--ink) 0%, #A85C7E 100%); -webkit-background-clip:text; background-clip:text; color:transparent; -webkit-text-fill-color:transparent;">Pradėkite nuo dviejų nemokamų treniruočių</h3>
        <p>Užpildykite formą ir susisieksime, kad suderintume laiką jums tinkamiausiai grupei.</p>
      </div>
      <div class="registration-panel">
        <form class="contact-form" id="registrationForm">
          <input type="text" name="vardas" placeholder="Mokinio vardas, pavardė" required>
          <input type="email" name="email" placeholder="El. paštas" required>
          <input type="tel" name="telefonas" placeholder="Telefono numeris" required>
          <select name="lokacija" required>
            <option value="" disabled selected>Pasirinkite lokaciją</option>
            <?= render_trial_location_options() ?>
          </select>
          <select name="gimimo_metai" id="gimimoMetai" class="is-empty">
            <option value="" disabled selected>Gimimo metai</option>
          </select>
          <textarea name="zinute" rows="3" placeholder="Žinutė (nebūtina)"></textarea>
          <button type="submit" class="btn btn-primary">Registruotis nemokamai</button>
          <div id="formStatus" class="form-status"></div>
        </form>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    <div class="footer-bottom">
      <span>© 2026 VšĮ Karate Ateitis. Visos teisės saugomos. · <a href="privatumas.php" style="color:inherit; text-decoration:underline;">Privatumo politika</a></span>
      <span>Nuo balto iki juodo diržo.</span>
    </div>
  </div>
</footer>

<script>

  // about section photo slider - auto-rotates
  const aboutSlides = document.querySelectorAll('#aboutSlider .slide');
  let aboutSlideIndex = 0;
  if(aboutSlides.length > 1){
    setInterval(()=>{
      aboutSlides[aboutSlideIndex].classList.remove('active');
      aboutSlideIndex = (aboutSlideIndex + 1) % aboutSlides.length;
      aboutSlides[aboutSlideIndex].classList.add('active');
    }, 4000);
  }

  // Elementas tolygiai atsiranda, kai pasiekiamas slenkant, ir toliau lieka matomas
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{
      if (e.isIntersecting) { e.target.classList.add('in-view'); io.unobserve(e.target); }
    });
  }, {threshold:0.12});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

  // group horizontal scroll window - native left/right scroll (trackpad/touch/wheel), plus buttons + dots as a convenience
  const groupScroll = document.getElementById('groupScroll');
  const groupSlideIds = ['vaikams','jaunimui','suaugusiems'];
  const groupDots = document.querySelectorAll('#groupTabs button');

  function scrollToGroup(i){
    const idx = ((i % groupSlideIds.length) + groupSlideIds.length) % groupSlideIds.length;
    groupScroll.scrollTo({left: idx * groupScroll.clientWidth, behavior:'smooth'});
    groupDots.forEach((d, di) => d.classList.toggle('active', di === idx));
  }
  document.getElementById('groupPrev').addEventListener('click', ()=>{
    scrollToGroup(Math.round(groupScroll.scrollLeft / groupScroll.clientWidth) - 1);
  });
  document.getElementById('groupNext').addEventListener('click', ()=>{
    scrollToGroup(Math.round(groupScroll.scrollLeft / groupScroll.clientWidth) + 1);
  });
  groupDots.forEach(d => d.addEventListener('click', ()=> scrollToGroup(parseInt(d.dataset.i))));

  // keep dots in sync while the person scrolls the window themselves
  let scrollTimeout;
  groupScroll.addEventListener('scroll', ()=>{
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(()=>{
      const idx = Math.round(groupScroll.scrollLeft / groupScroll.clientWidth);
      groupDots.forEach((d, i) => d.classList.toggle('active', i === idx));
    }, 80);
  }, {passive:true});

  // open directly on the right group if the URL has #vaikams / #jaunimui / #suaugusiems
  // Atidaro grupę pagal #vaikams / #jaunimui / #suaugusiems. Grupių sekcija yra pačiame viršuje,
  // todėl slenkame į puslapio pradžią - kad matytųsi ir pavadinimas „Kam ieškote treniruočių?“.
  function openGroup(id, smooth){
    const idx = groupSlideIds.indexOf(id);
    if(idx < 0) return false;
    // Karuselę perjungiame iškart, o puslapį į viršų slenkame atskirai (du lygiagretūs „smooth“ slinkimai vienas kitą nutraukia)
    groupScroll.style.scrollBehavior = 'auto';
    groupScroll.scrollLeft = idx * groupScroll.clientWidth;
    groupScroll.style.scrollBehavior = '';
    window.scrollTo({top:0, behavior: smooth ? 'smooth' : 'instant'});
    groupDots.forEach((d, i) => d.classList.toggle('active', i === idx));
    return true;
  }
  function openGroupFromHash(){
    const id = window.location.hash.replace('#','');
    if(id === 'grupes'){ window.scrollTo({top:0, behavior:'instant'}); return; }
    openGroup(id, false);
  }
  // Meniu „Treniruotės“ - į puslapio viršų, kur „Kam ieškote treniruočių?“
  document.querySelectorAll('a[href="#grupes"]').forEach(a => a.addEventListener('click', (e) => {
    e.preventDefault();
    history.replaceState(null, '', '#grupes');
    window.scrollTo({top:0, behavior:'smooth'});
  }));
  window.addEventListener('load', openGroupFromHash);
  window.addEventListener('hashchange', openGroupFromHash);
  // Meniu nuorodos į grupes veikia ir tada, kai adreso #... nesikeičia (pvz. du kartus iš eilės ta pati grupė)
  document.querySelectorAll('a[href="#vaikams"], a[href="#jaunimui"], a[href="#suaugusiems"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const id = a.getAttribute('href').slice(1);
      if(openGroup(id, true)){ e.preventDefault(); history.replaceState(null, '', '#' + id); }
    });
  });

  // location accordions - opening one closes its siblings, keeps the card height predictable
  document.querySelectorAll('.loc-accordion').forEach(group => {
    const items = group.querySelectorAll('details.loc-group');
    items.forEach(d => {
      d.addEventListener('toggle', () => {
        if (d.open) {
          items.forEach(other => { if (other !== d) other.open = false; });
        }
      });
    });
  });

  // vaikams schedule picker - click a location, its schedule appears in the panel on the right
  const schedulePanel = document.getElementById('vaikamsSchedulePanel');
  if (schedulePanel) {
    const allLocationItems = document.querySelectorAll('.picker-locations li[data-schedule]');
    allLocationItems.forEach(li => {
      li.addEventListener('click', () => {
        allLocationItems.forEach(other => other.classList.remove('active'));
        li.classList.add('active');
        schedulePanel.innerHTML =
          '<div class="kicker">Tvarkaraštis</div>' +
          '<div class="schedule-name">' + li.dataset.name + '</div>' +
          li.dataset.schedule;
      });
    });
  }

  // birth-year dropdown - filled here so the range lives in one place
  const yearSelect = document.getElementById('gimimoMetai');
  if (yearSelect) {
    const YEAR_NEWEST = new Date().getFullYear();
    const YEAR_OLDEST = 1940;
    for (let y = YEAR_NEWEST; y >= YEAR_OLDEST; y--) {
      const opt = document.createElement('option');
      opt.value = y;
      opt.textContent = y;
      yearSelect.appendChild(opt);
    }
    yearSelect.addEventListener('change', () => {
      yearSelect.classList.toggle('is-empty', yearSelect.value === '');
    });
  }

  // registration form -> sends to uzklausa.php (see accompanying file), falls back to a clear error if it can't reach it
  const regForm = document.getElementById('registrationForm');
  const formStatus = document.getElementById('formStatus');
  regForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    formStatus.textContent = 'Siunčiama...';
    formStatus.className = 'form-status';
    const submitBtn = regForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    try{
      const res = await fetch('uzklausa.php', {
        method:'POST',
        body:new FormData(regForm)
      });
      const data = await res.json().catch(()=>({ok:false}));
      if(res.ok && data.ok){
        formStatus.textContent = 'Ačiū! Susisieksime artimiausiu metu.';
        formStatus.className = 'form-status ok';
        regForm.reset();
        if (yearSelect) yearSelect.classList.add('is-empty');
      } else {
        throw new Error('send failed');
      }
    } catch(err){
      formStatus.textContent = 'Nepavyko išsiųsti. Parašykite mums tiesiogiai: info@karateka.lt';
      formStatus.className = 'form-status err';
    } finally {
      submitBtn.disabled = false;
    }
  });

</script>
<script src="<?= e(asset_url('assets/header.js')) ?>"></script>

</body>
</html>
