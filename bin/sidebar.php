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
        <i class="fa fa-bars"></i> &nbsp;<span 
        style="font-family: serif;"
        ><?=§('main-title')?>
    </button>
    <span class="w3-bar-item"><?=§($_GET['page'])?></span>
    <span class="w3-bar-item w3-right"><?=$contest['name'];?></span>
</div>
<nav class="w3-sidebar w3-white w3-animate-left" style="z-index:3;width:230px;display:none;" id="mySidebar">
    <br>
    <div class="w3-container w3-row">
        <div class="w3-col s4">
            <svg
                class="w3-margin-right"
                width="46"
                height="46"
                stroke-width="1.5"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M7 18V17C7 14.2386 9.23858 12 12 12V12C14.7614 12 17 14.2386 17 17V18"
                    stroke="currentColor"
                    stroke-linecap="round"
                />
                <path
                    d="M12 12C13.6569 12 15 10.6569 15 9C15 7.34315 13.6569 6 12 6C10.3431 6 9 7.34315 9 9C9 10.6569 10.3431 12 12 12Z"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <circle
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="1.5"
                />
            </svg>
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
        <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=password" 
        rel="noopener" class="w3-bar-item w3-button w3-padding <?=($_GET['page']!='password')?:'w3-blue'?>">
            <i class="fa-solid fa-key"></i>&nbsp; <?=§('password')?>
        </a>
        <br><br>
    </div>
</nav>
<div class="w3-overlay w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>