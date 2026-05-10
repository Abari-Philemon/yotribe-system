<?php
if (!isset($page_title)) {
    $page_title = 'Yotribe Farm System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

/* GLOBAL */
body{
    margin:0;
    background:#f4f7fb;
    font-family:system-ui;
}

/* SIDEBAR */
.sidebar{
    width:260px;
    height:100vh;
    position:fixed;
    top:0;
    left:0;
    background:#0f172a;
    color:#fff;
    padding:20px;
    overflow-y:auto;
}

/* MAIN */
.main{
    margin-left:260px;
    padding:20px;
}

/* MOBILE */
@media(max-width:768px){
    .sidebar{
        left:-260px;
        transition:.3s;
    }
    .sidebar.active{
        left:0;
    }
    .main{
        margin-left:0;
    }
}

/* UI */
.nav-link{
    color:#cbd5e1;
    padding:10px;
    border-radius:10px;
    display:block;
    margin-bottom:6px;
}
.nav-link:hover{background:#1e293b;color:#fff;}
.nav-link.active{background:#2563eb;color:#fff;}

.nav-title{
    font-size:12px;
    color:#94a3b8;
    margin-top:15px;
}

.quick-box{
    background:#1e293b;
    padding:10px;
    border-radius:12px;
    margin-bottom:15px;
}

.cardx{
    background:#fff;
    border-radius:15px;
    padding:15px;
    box-shadow:0 10px 20px rgba(0,0,0,.05);
}

.hero{
    background:linear-gradient(135deg,#0d6efd,#20c997);
    color:#fff;
    padding:20px;
    border-radius:15px;
}

</style>
</head>

<body>

<!-- MOBILE NAV -->
<button class="btn btn-primary d-md-none m-2" onclick="toggleSidebar()">☰ Menu</button>