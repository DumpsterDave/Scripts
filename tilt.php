<?php
    require_once('.\tiltoms.php');

    $Tilt = new TiltOMS();
    $Tilt->Set_Primary_Key('');
    $Tilt->Set_Workspace_Id('');

    $Entry = $Tilt->Generate_Entry_From_Post($_POST);

    $Tilt->Send_Log_Analytics_Data($Entry);
?>