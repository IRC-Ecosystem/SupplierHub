<?php
$ch = curl_init('http://localhost:8000/index.php?p=supplier&page=keuangan');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// login first? No, we will just login locally
// Wait, no we can't easily simulate login if we don't post to login.
// Let's just create a test login script.
