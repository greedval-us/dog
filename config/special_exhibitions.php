<?php

return [
    'sources' => [
        'titles' => 'https://help.rkf.online/ru/knowledge_base/art/2633/cat/489/polozenie-o-titulah-rkf',
        'shows' => 'https://help.rkf.online/ru/knowledge_base/art/2428/cat/489/polozenie-o-sertifikatnih-vistavkah-rkf-vstupaet-v-silu-s-01012026',
        'eurasia' => 'https://rkf.org.ru/vystavochnaja-dejatelnost/eurasia/',
        'russia' => 'https://rkf.org.ru/vystavochnaja-dejatelnost/russian/',
    ],
    'shows' => [
        'eurasia' => ['rank' => 'cac_rkf_special', 'legs' => ['president', 'cis'], 'winner' => ['EAW', 'EAJW', 'EAVW'], 'double_certificates' => ['CAC', 'JCAC', 'VCAC'], 'specialties' => true, 'source' => 'eurasia'],
        'russia' => ['rank' => 'cac_rkf_special', 'legs' => ['rkf_cup', 'moscow_cup'], 'winner' => ['RW', 'RJW', 'RVW'], 'double_certificates' => ['CAC', 'JCAC', 'VCAC'], 'specialties' => true, 'source' => 'russia'],
        'mazover' => ['rank' => 'cac_rkf_special', 'legs' => [''], 'winner' => [], 'double_certificates' => ['CAC', 'JCAC', 'VCAC'], 'specialties' => false, 'source' => 'titles'],
        'national_club' => ['rank' => 'specialty', 'legs' => [''], 'winner' => ['CLUB_WINNER', 'JCLUB_WINNER', 'VCLUB_WINNER'], 'double_certificates' => [], 'specialties' => false, 'source' => 'shows'],
        'national_breed' => ['rank' => 'specialty', 'legs' => [''], 'winner' => ['BREED_WINNER', 'JBREED_WINNER', 'VBREED_WINNER'], 'double_certificates' => [], 'specialties' => false, 'source' => 'shows'],
    ],
    'specialty_excluded_breeds' => ['german_shepherd', 'rottweiler'],
    'national_exchanges' => ['source' => 'titles', 'winner_codes' => ['EAW', 'RW'], 'double_count' => 2],
    'absolute_winners' => [
        'EAAW' => ['series' => 'eurasia', 'codes' => ['EAW', 'EAW'], 'legs' => ['president', 'cis'], 'source' => 'eurasia'],
        'EAJAW' => ['series' => 'eurasia', 'codes' => ['EAJW', 'EAJW'], 'legs' => ['president', 'cis'], 'source' => 'eurasia'],
        'EAVAW' => ['series' => 'eurasia', 'codes' => ['EAVW', 'EAVW'], 'legs' => ['president', 'cis'], 'source' => 'eurasia'],
        'RAW' => ['series' => 'russia', 'codes' => ['RW', 'MW'], 'legs' => ['rkf_cup', 'moscow_cup'], 'source' => 'russia'],
        'RJAW' => ['series' => 'russia', 'codes' => ['RJW', 'MJW'], 'legs' => ['rkf_cup', 'moscow_cup'], 'source' => 'russia'],
        'RVAW' => ['series' => 'russia', 'codes' => ['RVW', 'MVW'], 'legs' => ['rkf_cup', 'moscow_cup'], 'source' => 'russia'],
    ],
    'grand_championships' => [
        'GCH_RKF' => ['certificate' => 'CH_RKF_CERT', 'count' => 3, 'source' => 'titles'],
        'JGCH_RKF' => ['certificate' => 'JCH_RKF_CERT', 'count' => 2, 'source' => 'titles'],
        'VGCH_RKF' => ['certificate' => 'VCH_RKF_CERT', 'count' => 2, 'source' => 'titles'],
    ],
    'absolute_championships' => [
        'ACH_RKF' => ['EAAW', 'RAW'], 'JACH_RKF' => ['EAJAW', 'RJAW'], 'VACH_RKF' => ['EAVAW', 'RVAW'],
    ],
];
