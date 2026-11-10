<?php

return [

    /*
    | Period the desktop app loads by default. Format: YYYY/YYYY-GANJIL or YYYY/YYYY-GENAP.
    */
    'current_period' => env('SCHEDULING_PERIOD', '2026/2027-GANJIL'),

    /*
    | Most TPB SKS one lecturer should teach in a period. Placeholder until the institute
    | sets the real limit; the TPB dashboard only warns about lecturers above it.
    */
    'tpb_max_lecturer_sks' => (int) env('TPB_MAX_LECTURER_SKS', 15),

];
