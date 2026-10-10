<?php
/** Izjava o vođenju evidencije dezinfekcije – pregled za upravu (PDF), ista kao za inspekciju. */
if (!ima(P_DEZ_POSTAVKE) && !ima(P_DEZ_PREGLED)) {
    zabranjeno();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="izjava-evidencija-dezinfekcije.pdf"');
echo izjava_pdf(dez_stanice(false, 'F'));
exit;
