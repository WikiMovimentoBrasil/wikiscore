<script>
    let head = document.getElementsByTagName('HEAD')[0];
    let link = document.createElement('link');
    link.rel = 'stylesheet';
    link.type = 'text/css';
    link.href = 'https://tools-static.wmflabs.org/cdnjs/ajax/libs/font-awesome/6.2.0/css/all.css';
    head.appendChild(link);

    function w3_open() {
        var mySidebar = document.getElementById("mySidebar");
        var overlayBg = document.getElementById("myOverlay");
        if (mySidebar.style.display === 'block') {
            mySidebar.style.display = 'none';
            overlayBg.style.display = "none";
        } else {
            mySidebar.style.display = 'block';
            overlayBg.style.display = "block";
        }
    }
    function w3_close() {
        document.getElementById("mySidebar").style.display = "none";
        document.getElementById("myOverlay").style.display = "none";
    }
</script>
<?php if (!isset($_GET['page'])) $_GET['page'] = 'triage'; ?>
<div class="w3-<?=$contest['theme'];?> w3-large w3-bar w3-top" style="z-index:4">
    <button class="w3-bar-item w3-button w3-hover-none w3-hover-text-light-grey" onclick="w3_open();">
        <i class="fa fa-bars"></i> &nbsp;
        <img src="images/Logo_Branco.svg" alt="logo" class="w3-hide-medium w3-hide-small" style="width: 100px;">
    </button>
    <span class="w3-bar-item"><?=§($_GET['page'])?></span>
    <span class="w3-bar-item w3-right w3-hide-small"><?=$contest['name'];?></span>
</div>
<nav class="w3-sidebar w3-white w3-animate-left" style="z-index:3;width:230px;display:none;min-height:100vh;" id="mySidebar">
    <br>
    <div class="w3-container w3-row">
        <div class="w3-col s4">
            <i class="fa-solid fa-circle-user" style="font-size: 3em;"></i>
        </div>
        <div class="w3-col s8 w3-bar">
            <span><?=§('triage-welcome', ucfirst($_SESSION['user']['user_name']))?></span><br>
            <a href="javascript:document.getElementById('logout').submit()" class="w3-bar-item w3-button"><i class="fa-solid fa-door-open"></i></a>
            <form method="post" id="logout" style="display: none;">
                <input type="hidden" name="logout" value="Logout">
            </form>
        </div>
    </div>
    <hr>
    <div class="w3-container">
        <h5><?=§('triage-panel')?></h5>
    </div>
    <div class="w3-bar-block">
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=triage" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='triage')?:'w3-blue'?>">
            <i class="fa-solid fa-check-to-slot"></i>&nbsp; <?=§('triage')?>
        </a>
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=counter" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='counter')?:'w3-blue'?>">
            <i class="fa-solid fa-chart-line"></i>&nbsp; <?=§('counter')?>
        </a>
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=modify" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='modify')?:'w3-blue'?>">
            <i class="fa-solid fa-pen-to-square"></i>&nbsp; <?=§('modify')?>
        </a>
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=compare" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='compare')?:'w3-blue'?>">
            <i class="fa-solid fa-code-compare"></i>&nbsp; <?=§('compare')?>
        </a>
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=edits" target="_blank"
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='edits')?:'w3-blue'?>">
            <i class="fa-solid fa-list-check"></i>&nbsp; <?=§('triage-evaluated')?>
        </a>
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=backtrack" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='backtrack')?:'w3-blue'?>">
            <i class="fa-solid fa-history"></i>&nbsp; <?=§('backtrack')?>
        </a>
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=evaluators" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='evaluators')?:'w3-blue'?>">
            <i class="fa-solid fa-users"></i>&nbsp; <?=§('evaluators')?>
        </a>
        <a href="<?=$contest['endpoint'];?>?curid=<?=$contest['official_list_pageid'];?>" target="_blank"
        rel="noopener" class="w3-bar-item w3-button w3-padding">
            <i class="fa-solid fa-certificate"></i>&nbsp; <?=§('triage-list')?>
            <i class="fa-solid fa-arrow-up-right-from-square fa-xs"></i>
        </a>
        <a href="<?= ($contest['category_petscan'])
            ? "https://petscan.wmflabs.org/?psid={$contest['category_petscan']}"
            : "{$contest['endpoint']}?curid={$contest['category_pageid']}"
        ?>" target="_blank" rel="noopener" class="w3-bar-item w3-button w3-padding">
            <i class="fa-solid fa-magnifying-glass-chart"></i>&nbsp; <?=§('triage-cat')?> 
            <i class="fa-solid fa-arrow-up-right-from-square fa-xs"></i>
        </a>
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=password" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='password')?:'w3-blue'?>">
            <i class="fa-solid fa-key"></i>&nbsp; <?=§('password')?>
        </a>
        <br><br>
    </div>
</nav>
<div class="w3-overlay w3-animate-opacity" onclick="w3_close()" style="cursor:pointer;min-height:100vh;" title="close side menu" id="myOverlay"></div>