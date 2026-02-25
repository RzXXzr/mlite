<?php

namespace Plugins\Satu_Sehat;

use Systems\SiteModule;

class Site extends SiteModule
{
    public function routes()
    {
        $this->route('satu-sehat/encounter/(:any)', 'forwardEncounter');
        $this->route('satu-sehat/condition/(:any)', 'forwardCondition');
        $this->route('satu-sehat/observation/(:any)/(:any)', 'forwardObservation');
        $this->route('satu-sehat/procedure/(:any)', 'forwardProcedure');
        $this->route('satu-sehat/diet-gizi/(:any)', 'forwardDietGizi');
        $this->route('satu-sehat/vaksin/(:any)', 'forwardVaksin');
        $this->route('satu-sehat/care-plan/(:any)', 'forwardCarePlan');
        $this->route('satu-sehat/allergy/(:any)', 'forwardAllergy');
        $this->route('satu-sehat/questionnaire/(:any)', 'forwardQuestionnaire');
        $this->route('satu-sehat/clinical-impression/(:any)', 'forwardClinicalImpression');
        $this->route('satu-sehat/medication/(:any)/(:any)', 'forwardMedication');
        $this->route('satu-sehat/medication/(:any)', 'forwardMedication');
        $this->route('satu-sehat/laboratory/(:any)/(:any)', 'forwardLaboratory');
        $this->route('satu-sehat/laboratory/(:any)', 'forwardLaboratory');
        $this->route('satu-sehat/radiology/(:any)/(:any)', 'forwardRadiology');
        $this->route('satu-sehat/radiology/(:any)', 'forwardRadiology');
        $this->route('satu-sehat/forward-norawat/(:any)', 'forwardByNoRawat');
        $this->route('satu-sehat/forward-norawat', 'forwardByNoRawat');
        $this->route('satu-sehat/forward-tanggal/(:any)', 'forwardByDate');
        $this->route('satu-sehat/forward-tanggal', 'forwardByDate');
        $this->route('satu-sehat/batch-tanggal/(:any)', 'batchByDate');
        $this->route('satu-sehat/batch-tanggal', 'batchByDate');
        $this->route('satu-sehat/batch-plan', 'batchGetPlan');
        $this->route('satu-sehat/batch-process', 'batchProcessOne');
    }

    public function forwardEncounter($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getEncounter($no_rawat, false);
    }

    public function forwardCondition($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getCondition($no_rawat, false);
    }

    public function forwardObservation($no_rawat = null, $ttv = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($ttv === null && isset($_GET['ttv'])) {
            $ttv = $_GET['ttv'];
        }

        // Manual fix for router issue consuming slashes
        if ($ttv === null && strpos($no_rawat, '/') !== false) {
             $parts = explode('/', $no_rawat);
             $last = end($parts);
             if (in_array($last, ["tensi", "nadi", "respirasi", "suhu", "spo2", "gcs", "kesadaran", "berat", "tinggi", "perut"])) {
                 $ttv = $last;
                 array_pop($parts);
                 $no_rawat = implode('/', $parts);
             }
        }

        if ($no_rawat === null || $ttv === null) {
            echo json_encode(['error' => 'parameter kosong', 'no_rawat' => $no_rawat, 'ttv' => $ttv]);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getObservation($no_rawat, $ttv, false);
    }

    public function forwardProcedure($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getProcedure($no_rawat, false);
    }

    public function forwardDietGizi($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getDietGizi($no_rawat, false);
    }

    public function forwardVaksin($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getVaksin($no_rawat, false);
    }

    public function forwardClinicalImpression($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getClinicalImpression($no_rawat, false);
    }

    public function forwardMedication($no_rawat = null, $tipe = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($tipe === null && isset($_GET['tipe'])) {
            $tipe = $_GET['tipe'];
        }

        // Manual fix for router issue consuming slashes
        if ($tipe === null && strpos($no_rawat, '/') !== false) {
             $parts = explode('/', $no_rawat);
             $last = end($parts);
             if (in_array($last, ['request', 'dispense', 'statement'])) {
                 $tipe = $last;
                 array_pop($parts);
                 $no_rawat = implode('/', $parts);
             }
        }

        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        if ($tipe === null) {
            $tipe = 'request';
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getMedication((string)$no_rawat, (string)$tipe, false);
    }

    public function forwardCarePlan($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getCarePlan($no_rawat, false);
    }

    public function forwardAllergy($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getAllergy($no_rawat, false);
    }    

    public function forwardQuestionnaire($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getQuestionnaire($no_rawat, false);
    }    

    public function forwardLaboratory($no_rawat = null, $tipe = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($tipe === null && isset($_GET['tipe'])) {
            $tipe = $_GET['tipe'];
        }

        // Manual fix for router issue consuming slashes
        if ($tipe === null && strpos($no_rawat, '/') !== false) {
             $parts = explode('/', $no_rawat);
             $last = end($parts);
             if (in_array($last, ['request', 'specimen', 'observation', 'diagnostic'])) {
                 $tipe = $last;
                 array_pop($parts);
                 $no_rawat = implode('/', $parts);
             }
        }

        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        if ($tipe === null) {
            $tipe = 'request';
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getLaboratory((string)$no_rawat, (string)$tipe, false);
    }

    public function forwardRadiology($no_rawat = null, $tipe = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($tipe === null && isset($_GET['tipe'])) {
            $tipe = $_GET['tipe'];
        }
        
        // Manual fix for router issue consuming slashes
        if ($tipe === null && strpos($no_rawat, '/') !== false) {
             $parts = explode('/', $no_rawat);
             $last = end($parts);
             if (in_array($last, ['request', 'specimen', 'observation', 'diagnostic', 'image'])) {
                 $tipe = $last;
                 array_pop($parts);
                 $no_rawat = implode('/', $parts);
             }
        }

        if ($no_rawat === null) {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }
        if ($tipe === null) {
            $tipe = 'request';
        }
        $admin = new \Plugins\Satu_Sehat\Admin($this->core);
        $admin->init();
        return $admin->getRadiology((string)$no_rawat, (string)$tipe, false);
    }

    public function forwardByDate($tanggal = null)
    {
        if ($tanggal === null && isset($_GET['tanggal'])) {
            $tanggal = $_GET['tanggal'];
        }
        if ($tanggal === null) {
            $tanggal = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }
        $rows = $this->db('reg_periksa')
            ->select('no_rawat')
            ->where('reg_periksa.tgl_registrasi', $tanggal)
            ->where('stts', '!=', 'Batal')
            ->toArray();
        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'display' => $r['no_rawat'],
                'url' => str_replace('/', '', $r['no_rawat'])
            ];
        }
        $encBase = '/satu-sehat/encounter/';
        $condBase = '/satu-sehat/condition/';
        $obsBase = '/satu-sehat/observation/';
        $procBase = '/satu-sehat/procedure/';
        $impBase = '/satu-sehat/clinical-impression/';
        $vaxBase = '/satu-sehat/vaksin/';
        $dietBase = '/satu-sehat/diet-gizi/';
        $careBase = '/satu-sehat/care-plan/';
        $allergyBase = '/satu-sehat/allergy/';
        $questionnaireBase = '/satu-sehat/questionnaire/';
        $medBase = '/satu-sehat/medication/';
        $labBase = '/satu-sehat/laboratory/';
        $radBase = '/satu-sehat/radiology/';

        echo '<!doctype html>
        <html>
        <head>
        <meta charset="utf-8">
        <title>Forward Satu Sehat</title>
        <style>
            body { font-family: system-ui, Arial, sans-serif; }
            .item { border:1px solid #ddd; padding:8px; margin:8px 0; }
            .label { font-weight:bold; }
            pre { white-space:pre-wrap; word-wrap:break-word; background:#f7f7f7; padding:8px; border-radius:4px; }
            .summary { margin-top:16px; border-top:2px solid #ccc; padding-top:12px; }
        </style>
        </head>

        <body>

        <form method="get" action="/satu-sehat/forward-tanggal" style="margin-bottom:12px"><label>Tanggal : </label> <input type="date" name="tanggal" value="' . htmlspecialchars($tanggal, ENT_QUOTES) . '" /> <button type="submit">Proses</button></form>
        <h3>Proses tanggal ' . $tanggal . '</h3>

        <div id="log"></div>

        <div class="summary">
            <div class="label">Ringkasan JSON</div>
            <pre id="summary"></pre>
        </div>

        <script>
        const list = ' . json_encode($list) . ';
        const ttv = ["tensi", "nadi", "respirasi", "suhu", "spo2", "gcs", "kesadaran", "berat", "tinggi", "perut"];

        async function call(url) {
            try {
                const r = await fetch(url);
                return await r.text();
            } catch (e) {
                return String(e);
            }
        }

        function toJsonOrString(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                try {
                    return JSON.parse(text.replace(/`/g, ""));
                } catch (e2) {
                    return text;
                }
            }
        }

        async function run() {
            const log = document.getElementById("log");
            const summary = document.getElementById("summary");
            const results = [];

            for (const item of list) {
                const nrDisp = item.display;
                const nrUrl = item.url;

                const container = document.createElement("div");
                container.className = "item";
                container.innerHTML = `<div class="label">No Rawat: ${nrDisp}</div>`;
                log.appendChild(container);

                // Encounter
                const encTxt = await call("' . $encBase . '" + nrUrl);
                const enc = toJsonOrString(encTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Encounter:<pre>" + (typeof enc === "string" ? enc : JSON.stringify(enc, null, 2)) + "</pre></div>"
                );

                // Condition
                const condTxt = await call("' . $condBase . '" + nrUrl);
                const cond = toJsonOrString(condTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Condition:<pre>" + (typeof cond === "string" ? cond : JSON.stringify(cond, null, 2)) + "</pre></div>"
                );

                // Observations
                const obsRes = {};
                for (const t of ttv) {
                    const obsTxt = await call("' . $obsBase . '" + nrUrl + "/" + t);
                    const obs = toJsonOrString(obsTxt);
                    obsRes[t] = obs;

                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Observation (" + t + "):<pre>" +
                            (typeof obs === "string" ? obs : JSON.stringify(obs, null, 2)) +
                        "</pre></div>"
                    );
                }

                // Procedure
                const procTxt = await call("' . $procBase . '" + nrUrl);
                const proc = toJsonOrString(procTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Procedure:<pre>" + (typeof proc === "string" ? proc : JSON.stringify(proc, null, 2)) + "</pre></div>"
                );

                // Clinical Impression
                const impTxt = await call("' . $impBase . '" + nrUrl);
                const imp = toJsonOrString(impTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Clinical Impression:<pre>" + (typeof imp === "string" ? imp : JSON.stringify(imp, null, 2)) + "</pre></div>"
                );

                // Vaksin
                const vaxTxt = await call("' . $vaxBase . '" + nrUrl);
                const vax = toJsonOrString(vaxTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Vaksin:<pre>" + (typeof vax === "string" ? vax : JSON.stringify(vax, null, 2)) + "</pre></div>"
                );

                // Diet Gizi
                const dietTxt = await call("' . $dietBase . '" + nrUrl);
                const diet = toJsonOrString(dietTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Diet Gizi:<pre>" + (typeof diet === "string" ? diet : JSON.stringify(diet, null, 2)) + "</pre></div>"
                );

                // Care Plan
                const careTxt = await call("' . $careBase . '" + nrUrl);
                const care = toJsonOrString(careTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Care Plan:<pre>" + (typeof care === "string" ? care : JSON.stringify(care, null, 2)) + "</pre></div>"
                );

                // Allergy
                const allergyTxt = await call("' . $allergyBase . '" + nrUrl);
                const allergy = toJsonOrString(allergyTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Allergy:<pre>" + (typeof allergy === "string" ? allergy : JSON.stringify(allergy, null, 2)) + "</pre></div>"
                );


                // Questionnaire
                const questionnaireTxt = await call("' . $questionnaireBase . '" + nrUrl);
                const questionnaire = toJsonOrString(questionnaireTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Questionnaire:<pre>" + (typeof questionnaire === "string" ? questionnaire : JSON.stringify(questionnaire, null, 2)) + "</pre></div>"
                );
                
                // Medication (request/dispense/statement)
                const medTypes = ["request", "dispense", "statement"];
                const medRes = {};
                for (const mt of medTypes) {
                    const mtTxt = await call("' . $medBase . '" + nrUrl + "/" + mt);
                    const mtJson = toJsonOrString(mtTxt);
                    medRes[mt] = mtJson;
                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Medication (" + mt + "):<pre>" + (typeof mtJson === "string" ? mtJson : JSON.stringify(mtJson, null, 2)) + "</pre></div>"
                    );
                }

                // Laboratory (request/specimen/result)
                const labTypes = ["request", "specimen", "observation", "diagnostic"];
                const labRes = {};
                for (const lt of labTypes) {
                    const ltTxt = await call("' . $labBase . '" + nrUrl + "/" + lt);
                    const ltJson = toJsonOrString(ltTxt);
                    labRes[lt] = ltJson;
                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Laboratory (" + lt + "):<pre>" + (typeof ltJson === "string" ? ltJson : JSON.stringify(ltJson, null, 2)) + "</pre></div>"
                    );
                }

                // Radiology (request/result)
                const radTypes = ["request", "specimen", "observation", "diagnostic", "image"];
                const radRes = {};
                for (const rt of radTypes) {
                    const rtTxt = await call("' . $radBase . '" + nrUrl + "/" + rt);
                    const rtJson = toJsonOrString(rtTxt);
                    radRes[rt] = rtJson;
                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Radiology (" + rt + "):<pre>" + (typeof rtJson === "string" ? rtJson : JSON.stringify(rtJson, null, 2)) + "</pre></div>"
                    );
                }
                    
                results.push({
                    no_rawat: nrDisp,
                    encounter: enc,
                    condition: cond,
                    observation: obsRes,
                    procedure: proc,
                    clinical_impression: imp,
                    vaksin: vax,
                    diet_gizi: diet,
                    care_plan: care,
                    allergy: allergy,
                    questionnaire: questionnaire,
                    medication: medRes,
                    laboratory: labRes,
                    radiology: radRes
                });

                summary.textContent = JSON.stringify(results, null, 2);
            }

            summary.textContent = JSON.stringify(results, null, 2);
        }

        run();
        </script>

        </body>
        </html>';

        echo '</body></html>';
        exit();
    }



    public function forwardByNoRawat($no_rawat = null)
    {
        if ($no_rawat === null && isset($_GET['no_rawat'])) {
            $no_rawat = $_GET['no_rawat'];
        }
        if ($no_rawat === null) {
            $no_rawat = '';
        }
        $list = [];
        if ($no_rawat !== '') {
            $list[] = [
                'display' => $no_rawat,
                'url' => str_replace('/', '', $no_rawat)
            ];
        }

        $encBase = '/satu-sehat/encounter/';
        $condBase = '/satu-sehat/condition/';
        $obsBase = '/satu-sehat/observation/';
        $procBase = '/satu-sehat/procedure/';
        $impBase = '/satu-sehat/clinical-impression/';
        $vaxBase = '/satu-sehat/vaksin/';
        $dietBase = '/satu-sehat/diet-gizi/';
        $careBase = '/satu-sehat/care-plan/';
        $allergyBase = '/satu-sehat/allergy/';
        $questionnaireBase = '/satu-sehat/questionnaire/';
        $medBase = '/satu-sehat/medication/';
        $labBase = '/satu-sehat/laboratory/';
        $radBase = '/satu-sehat/radiology/';

        echo '<!doctype html>
        <html>
        <head>
        <meta charset="utf-8">
        <title>Forward Satu Sehat</title>
        <style>
            body { font-family: system-ui, Arial, sans-serif; }
            .item { border:1px solid #ddd; padding:8px; margin:8px 0; }
            .label { font-weight:bold; }
            pre { white-space:pre-wrap; word-wrap:break-word; background:#f7f7f7; padding:8px; border-radius:4px; }
            .summary { margin-top:16px; border-top:2px solid #ccc; padding-top:12px; }
        </style>
        </head>

        <body>

        <h3>Proses no_rawat ' . $no_rawat . '</h3>

        <div id="log"></div>

        <div class="summary">
            <div class="label">Ringkasan JSON</div>
            <pre id="summary"></pre>
        </div>

        <script>
        const list = ' . json_encode($list) . ';
        const ttv = ["tensi", "nadi", "respirasi", "suhu", "spo2", "gcs", "kesadaran", "berat", "tinggi", "perut"];

        async function call(url) {
            try {
                const r = await fetch(url);
                return await r.text();
            } catch (e) {
                return String(e);
            }
        }

        function toJsonOrString(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                try {
                    return JSON.parse(text.replace(/`/g, ""));
                } catch (e2) {
                    return text;
                }
            }
        }

        async function run() {
            const log = document.getElementById("log");
            const summary = document.getElementById("summary");
            const results = [];

            for (const item of list) {
                const nrDisp = item.display;
                const nrUrl = item.url;

                const container = document.createElement("div");
                container.className = "item";
                container.innerHTML = `<div class="label">No Rawat: ${nrDisp}</div>`;
                log.appendChild(container);

                // Encounter
                const encTxt = await call("' . $encBase . '" + nrUrl);
                const enc = toJsonOrString(encTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Encounter:<pre>" + (typeof enc === "string" ? enc : JSON.stringify(enc, null, 2)) + "</pre></div>"
                );

                // Condition
                const condTxt = await call("' . $condBase . '" + nrUrl);
                const cond = toJsonOrString(condTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Condition:<pre>" + (typeof cond === "string" ? cond : JSON.stringify(cond, null, 2)) + "</pre></div>"
                );

                // Observations
                const obsRes = {};
                for (const t of ttv) {
                    const obsTxt = await call("' . $obsBase . '" + nrUrl + "/" + t);
                    const obs = toJsonOrString(obsTxt);
                    obsRes[t] = obs;

                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Observation (" + t + "):<pre>" +
                            (typeof obs === "string" ? obs : JSON.stringify(obs, null, 2)) +
                        "</pre></div>"
                    );
                }

                // Procedure
                const procTxt = await call("' . $procBase . '" + nrUrl);
                const proc = toJsonOrString(procTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Procedure:<pre>" + (typeof proc === "string" ? proc : JSON.stringify(proc, null, 2)) + "</pre></div>"
                );

                // Clinical Impression
                const impTxt = await call("' . $impBase . '" + nrUrl);
                const imp = toJsonOrString(impTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Clinical Impression:<pre>" + (typeof imp === "string" ? imp : JSON.stringify(imp, null, 2)) + "</pre></div>"
                );

                // Vaksin
                const vaxTxt = await call("' . $vaxBase . '" + nrUrl);
                const vax = toJsonOrString(vaxTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Vaksin:<pre>" + (typeof vax === "string" ? vax : JSON.stringify(vax, null, 2)) + "</pre></div>"
                );

                // Diet Gizi
                const dietTxt = await call("' . $dietBase . '" + nrUrl);
                const diet = toJsonOrString(dietTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Diet Gizi:<pre>" + (typeof diet === "string" ? diet : JSON.stringify(diet, null, 2)) + "</pre></div>"
                );

                // Care Plan
                const careTxt = await call("' . $careBase . '" + nrUrl);
                const care = toJsonOrString(careTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Care Plan:<pre>" + (typeof care === "string" ? care : JSON.stringify(care, null, 2)) + "</pre></div>"
                );

                // Allergy
                const allergyTxt = await call("' . $allergyBase . '" + nrUrl);
                const allergy = toJsonOrString(allergyTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Allergy:<pre>" + (typeof allergy === "string" ? allergy : JSON.stringify(allergy, null, 2)) + "</pre></div>"
                );

                // Questionnaire
                const questionnaireTxt = await call("' . $questionnaireBase . '" + nrUrl);
                const questionnaire = toJsonOrString(questionnaireTxt);
                container.insertAdjacentHTML(
                    "beforeend",
                    "<div>Questionnaire:<pre>" + (typeof questionnaire === "string" ? questionnaire : JSON.stringify(questionnaire, null, 2)) + "</pre></div>"
                );

                // Medication (request/dispense/statement)
                const medTypes = ["request", "dispense", "statement"];
                const medRes = {};
                for (const mt of medTypes) {
                    const mtTxt = await call("' . $medBase . '" + nrUrl + "/" + mt);
                    const mtJson = toJsonOrString(mtTxt);
                    medRes[mt] = mtJson;
                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Medication (" + mt + "):<pre>" + (typeof mtJson === "string" ? mtJson : JSON.stringify(mtJson, null, 2)) + "</pre></div>"
                    );
                }

                // Laboratory (request/specimen/result)
                const labTypes = ["request", "specimen", "observation", "diagnostic"];
                const labRes = {};
                for (const lt of labTypes) {
                    const ltTxt = await call("' . $labBase . '" + nrUrl + "/" + lt);
                    const ltJson = toJsonOrString(ltTxt);
                    labRes[lt] = ltJson;
                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Laboratory (" + lt + "):<pre>" + (typeof ltJson === "string" ? ltJson : JSON.stringify(ltJson, null, 2)) + "</pre></div>"
                    );
                }

                // Radiology (request/result)
                const radTypes = ["request", "specimen", "observation", "diagnostic", "image"];
                const radRes = {};
                for (const rt of radTypes) {
                    const rtTxt = await call("' . $radBase . '" + nrUrl + "/" + rt);
                    const rtJson = toJsonOrString(rtTxt);
                    radRes[rt] = rtJson;
                    container.insertAdjacentHTML(
                        "beforeend",
                        "<div>Radiology (" + rt + "):<pre>" + (typeof rtJson === "string" ? rtJson : JSON.stringify(rtJson, null, 2)) + "</pre></div>"
                    );
                }
                    
                results.push({
                    no_rawat: nrDisp,
                    encounter: enc,
                    condition: cond,
                    observation: obsRes,
                    procedure: proc,
                    clinical_impression: imp,
                    vaksin: vax,
                    diet_gizi: diet,
                    care_plan: care,
                    allergy: allergy,
                    questionnaire: questionnaire,
                    medication: medRes,
                    laboratory: labRes,
                    radiology: radRes
                });

                summary.textContent = JSON.stringify(results, null, 2);
            }

            summary.textContent = JSON.stringify(results, null, 2);
        }

        run();
        </script>

        </body>
        </html>';

        echo '</body></html>';
        exit();
    }

    // =========================================================================
    //  BATCH BY DATE — Load & verify first, send on user action (AJAX)
    // =========================================================================

    public function batchByDate($tanggal = null)
    {
        // Support date range: tanggal (or tanggal_dari) + tanggal_sampai
        $tanggalDari = $tanggal ?? $_GET['tanggal'] ?? $_GET['tanggal_dari'] ?? date('Y-m-d');
        $tanggalSampai = $_GET['tanggal_sampai'] ?? null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDari)) {
            $tanggalDari = date('Y-m-d');
        }
        if ($tanggalSampai !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSampai)) {
            $tanggalSampai = null;
        }
        // Ensure dari <= sampai
        if ($tanggalSampai !== null && $tanggalSampai < $tanggalDari) {
            [$tanggalDari, $tanggalSampai] = [$tanggalSampai, $tanggalDari];
        }
        // If same day, treat as single day
        if ($tanggalSampai === $tanggalDari) {
            $tanggalSampai = null;
        }

        $force = !empty($_GET['force']);

        require_once __DIR__ . '/src/BatchProcessor.php';
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        $baseUrl = $scheme . '://' . $host;
        $processor = new \Plugins\Satu_Sehat\Src\BatchProcessor($this->core, $baseUrl);

        // Phase 1: Load all data (no API calls)
        $rows = $processor->getNoRawatByDate($tanggalDari, $tanggalSampai);
        $items = [];

        // B. Cache pasien/dokter — avoid duplicate queries for same person
        $cachePasien = [];
        $cacheDokter = [];

        foreach ($rows as $row) {
            $no_rawat = $row['no_rawat'];
            $existing = $processor->getExistingResponse($no_rawat);
            $dataAvail = $processor->getDataAvailability($no_rawat, $row['status_lanjut']);
            $plan = $processor->determineResources($existing, $dataAvail, $force);

            $toSend = 0;
            $alreadySent = 0;
            $noData = 0;
            foreach ($plan as $p) {
                if ($p['action'] === 'send') $toSend++;
                elseif ($p['action'] === 'skip_exists') $alreadySent++;
                else $noData++;
            }

            // Cached pasien lookup
            $noRkm = $row['no_rkm_medis'] ?? '';
            if (!isset($cachePasien[$noRkm])) {
                $cachePasien[$noRkm] = $this->core->getPasienInfo('nm_pasien', $noRkm);
            }
            // Cached dokter lookup
            $kdDokter = $row['kd_dokter'] ?? '';
            if (!isset($cacheDokter[$kdDokter])) {
                $cacheDokter[$kdDokter] = $this->core->getDokterInfo('nm_dokter', $kdDokter);
            }

            // C. No 'plan' in items — loaded on-demand via AJAX
            $items[] = [
                'no_rawat'       => $no_rawat,
                'no_rawat_url'   => str_replace('/', '', $no_rawat),
                'nm_pasien'      => $cachePasien[$noRkm],
                'nm_dokter'      => $cacheDokter[$kdDokter],
                'status_lanjut'  => $row['status_lanjut'],
                'tgl_registrasi' => $row['tgl_registrasi'] ?? $tanggalDari,
                'to_send'        => $toSend,
                'already_sent'   => $alreadySent,
                'no_data'        => $noData,
                'has_encounter'  => !empty($existing['id_encounter']),
            ];
        }

        $totalRows = count($items);
        $totalToSend = array_sum(array_column($items, 'to_send'));
        $totalAlready = array_sum(array_column($items, 'already_sent'));

        // Render HTML
        echo $this->batchRenderPage($tanggalDari, $tanggalSampai, $items, $totalRows, $totalToSend, $totalAlready, $force);
        exit();
    }

    /**
     * AJAX endpoint: process one no_rawat server-side, return JSON.
     * Called by the browser JS loop after user clicks "Mulai Kirim".
     */
    public function batchProcessOne()
    {
        header('Content-Type: application/json; charset=utf-8');

        $no_rawat = $_GET['no_rawat'] ?? '';
        $status_lanjut = $_GET['status_lanjut'] ?? 'Ralan';
        $force = !empty($_GET['force']);

        if ($no_rawat === '') {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }

        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        $baseUrl = $scheme . '://' . $host;

        require_once __DIR__ . '/src/BatchProcessor.php';
        $processor = new \Plugins\Satu_Sehat\Src\BatchProcessor($this->core, $baseUrl);

        $result = $processor->processNoRawat($no_rawat, $status_lanjut, $force);

        echo json_encode($result, JSON_UNESCAPED_SLASHES);
        exit();
    }

    /**
     * AJAX endpoint: return plan detail for a single no_rawat (lazy-load).
     */
    public function batchGetPlan()
    {
        header('Content-Type: application/json; charset=utf-8');

        $no_rawat = $_GET['no_rawat'] ?? '';
        $status_lanjut = $_GET['status_lanjut'] ?? 'Ralan';
        $force = !empty($_GET['force']);

        if ($no_rawat === '') {
            echo json_encode(['error' => 'no_rawat kosong']);
            exit();
        }

        require_once __DIR__ . '/src/BatchProcessor.php';
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        $baseUrl = $scheme . '://' . $host;
        $processor = new \Plugins\Satu_Sehat\Src\BatchProcessor($this->core, $baseUrl);

        $existing = $processor->getExistingResponse($no_rawat);
        $dataAvail = $processor->getDataAvailability($no_rawat, $status_lanjut);
        $plan = $processor->determineResources($existing, $dataAvail, $force);

        echo json_encode($plan, JSON_UNESCAPED_SLASHES);
        exit();
    }

    // =========================================================================
    //  Batch HTML Renderer
    // =========================================================================

    private function batchRenderPage(string $tanggalDari, ?string $tanggalSampai, array $items, int $total, int $totalToSend, int $totalAlready, bool $force): string
    {
        $forceChecked = $force ? 'checked' : '';
        $itemsJson = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS);
        $isRange = ($tanggalSampai !== null);
        $dateLabel = $isRange
            ? htmlspecialchars($tanggalDari) . ' s/d ' . htmlspecialchars($tanggalSampai)
            : htmlspecialchars($tanggalDari);
        $titleLabel = $isRange
            ? htmlspecialchars($tanggalDari) . ' — ' . htmlspecialchars($tanggalSampai)
            : htmlspecialchars($tanggalDari);

        return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Batch Satu Sehat — ' . $titleLabel . '</title>
<style>
:root {
    --green: #22c55e; --yellow: #eab308; --red: #ef4444; --gray: #9ca3af; --blue: #3b82f6;
    --green-bg: #f0fdf4; --yellow-bg: #fefce8; --red-bg: #fef2f2; --gray-bg: #f9fafb; --blue-bg: #eff6ff;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: #f3f4f6; color: #1f2937; padding: 16px; max-width: 960px; margin: 0 auto; }
h2 { font-size: 1.4rem; margin-bottom: 12px; }

.toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; padding: 12px 16px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.toolbar label { font-weight: 600; font-size: .9rem; }
.toolbar input[type=date] { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: .9rem; }
.toolbar .cb { display: flex; align-items: center; gap: 4px; font-size: .85rem; }
.btn { padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: .9rem; font-weight: 600; color: #fff; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-blue { background: #2563eb; } .btn-blue:hover:not(:disabled) { background: #1d4ed8; }
.btn-green { background: #16a34a; } .btn-green:hover:not(:disabled) { background: #15803d; }
.btn-red { background: #ef4444; } .btn-red:hover:not(:disabled) { background: #dc2626; }
.btn-yellow { background: #f59e0b; } .btn-yellow:hover:not(:disabled) { background: #d97706; }

/* Info banner */
.info-banner { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .9rem; display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
.info-banner.loading { background: var(--blue-bg); color: #1e40af; }
.info-banner.ready { background: var(--green-bg); color: #166534; }

/* Progress */
.progress-wrap { background: #fff; padding: 12px 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 16px; position: sticky; top: 0; z-index: 10; display: none; }
.progress-bar-outer { height: 22px; background: #e5e7eb; border-radius: 11px; overflow: hidden; margin: 8px 0; }
.progress-bar-inner { height: 100%; background: linear-gradient(90deg, #2563eb, #3b82f6); border-radius: 11px; transition: width .3s; width: 0%; display: flex; align-items: center; justify-content: center; font-size: .75rem; color: #fff; font-weight: 700; min-width: 36px; }
.stats { display: flex; gap: 16px; font-size: .85rem; flex-wrap: wrap; }
.stat { display: flex; align-items: center; gap: 4px; }
.dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.dot-green { background: var(--green); } .dot-yellow { background: var(--yellow); }
.dot-red { background: var(--red); } .dot-gray { background: var(--gray); } .dot-blue { background: var(--blue); }

/* Cards */
.card { background: #fff; border-radius: 8px; margin-bottom: 6px; box-shadow: 0 1px 2px rgba(0,0,0,.06); border-left: 4px solid #e5e7eb; overflow: hidden; }
.card.st-success { border-left-color: var(--green); }
.card.st-partial { border-left-color: var(--yellow); }
.card.st-failed  { border-left-color: var(--red); }
.card.st-skipped { border-left-color: var(--gray); }
.card.st-ready   { border-left-color: var(--blue); }
.card.st-complete { border-left-color: var(--green); }
.card-header { padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
.card-header:hover { background: #f9fafb; }
.card-title { font-weight: 600; font-size: .88rem; }
.card-title small { font-weight: 400; color: #6b7280; margin-left: 6px; font-size: .82rem; }
.card-meta { display: flex; gap: 10px; font-size: .78rem; color: #6b7280; align-items: center; }
.badge { font-size: .72rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
.badge-send    { background: var(--blue-bg); color: #1e40af; }
.badge-done    { background: var(--green-bg); color: #166534; }
.badge-nodata  { background: var(--gray-bg); color: #4b5563; }
.badge-success { background: var(--green-bg); color: #166534; }
.badge-partial { background: var(--yellow-bg); color: #854d0e; }
.badge-failed  { background: var(--red-bg); color: #991b1b; }
.badge-skipped { background: var(--gray-bg); color: #4b5563; }
.badge-processing { background: #dbeafe; color: #1e40af; }
.card-body { padding: 0 14px 10px; display: none; }
.card.open .card-body { display: block; }
.res-line { display: flex; align-items: center; gap: 6px; padding: 3px 0; font-size: .82rem; border-bottom: 1px solid #f3f4f6; }
.res-line:last-child { border-bottom: none; }
.res-icon { font-size: .9rem; flex-shrink: 0; }
.res-label { flex: 1; }
.res-detail { color: #6b7280; font-size: .78rem; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Summary */
.summary { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-top: 16px; display: none; }
.summary h3 { margin-bottom: 8px; }
.summary table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.summary td, .summary th { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
</style>
</head>
<body>

<h2>Batch Kirim Satu Sehat</h2>

<div class="toolbar">
    <label>Dari:</label>
    <input type="date" id="inputDari" value="' . htmlspecialchars($tanggalDari) . '">
    <label>Sampai:</label>
    <input type="date" id="inputSampai" value="' . htmlspecialchars($tanggalSampai ?? $tanggalDari) . '">
    <div class="cb"><input type="checkbox" id="forceCheck" ' . $forceChecked . '> <label for="forceCheck">Force ulang</label></div>
    <button class="btn btn-blue" onclick="loadData()">Load Data</button>
    <button class="btn btn-green" id="btnStart" onclick="startSend()" disabled>▶ Mulai Kirim</button>
    <button class="btn btn-red" id="btnStop" onclick="stopSend()" style="display:none">⏹ Stop</button>
</div>

<div class="info-banner ready" id="infoBanner">
    <span>📋 <strong>' . $total . '</strong> no rawat ditemukan untuk tanggal ' . $dateLabel . '</span>
    <span>📤 <strong>' . $totalToSend . '</strong> resource perlu dikirim</span>
    <span>✅ <strong>' . $totalAlready . '</strong> sudah terkirim</span>
</div>

<div class="progress-wrap" id="progressWrap">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <span id="progressText" style="font-weight:600; font-size:.9rem;">Siap kirim</span>
        <span id="progressPct" style="font-size:.85rem; color:#6b7280;">0%</span>
    </div>
    <div class="progress-bar-outer">
        <div class="progress-bar-inner" id="progressBar">0%</div>
    </div>
    <div class="stats">
        <div class="stat"><span class="dot dot-green"></span> Sukses: <strong id="cntSuccess">0</strong></div>
        <div class="stat"><span class="dot dot-yellow"></span> Partial: <strong id="cntPartial">0</strong></div>
        <div class="stat"><span class="dot dot-red"></span> Gagal: <strong id="cntFailed">0</strong></div>
        <div class="stat"><span class="dot dot-gray"></span> Skip: <strong id="cntSkipped">0</strong></div>
    </div>
</div>

<div id="cards"></div>

<div class="summary" id="summaryDiv"></div>

<script>
const ITEMS = ' . $itemsJson . ';
let running = false;
let stopped = false;
const planCache = {};

// Rate limiter: 200 req/min → min 310ms between requests
const RATE_MIN_INTERVAL = 310;
let lastRequestTime = 0;
async function rateLimitWait() {
    const now = Date.now();
    const elapsed = now - lastRequestTime;
    if (elapsed < RATE_MIN_INTERVAL) {
        await new Promise(r => setTimeout(r, RATE_MIN_INTERVAL - elapsed));
    }
    lastRequestTime = Date.now();
}

function buildResourceUrl(key, nr) {
    const m = {
        encounter: "/satu-sehat/encounter/" + nr,
        condition: "/satu-sehat/condition/" + nr,
        obs_tensi: "/satu-sehat/observation/" + nr + "/tensi",
        obs_nadi: "/satu-sehat/observation/" + nr + "/nadi",
        obs_respirasi: "/satu-sehat/observation/" + nr + "/respirasi",
        obs_suhu: "/satu-sehat/observation/" + nr + "/suhu",
        obs_spo2: "/satu-sehat/observation/" + nr + "/spo2",
        obs_gcs: "/satu-sehat/observation/" + nr + "/gcs",
        obs_kesadaran: "/satu-sehat/observation/" + nr + "/kesadaran",
        obs_berat: "/satu-sehat/observation/" + nr + "/berat",
        obs_tinggi: "/satu-sehat/observation/" + nr + "/tinggi",
        obs_perut: "/satu-sehat/observation/" + nr + "/perut",
        procedure: "/satu-sehat/procedure/" + nr,
        clinical_impression: "/satu-sehat/clinical-impression/" + nr,
        vaksin: "/satu-sehat/vaksin/" + nr,
        diet_gizi: "/satu-sehat/diet-gizi/" + nr,
        care_plan: "/satu-sehat/care-plan/" + nr,
        allergy: "/satu-sehat/allergy/" + nr,
        questionnaire: "/satu-sehat/questionnaire/" + nr,
        med_request: "/satu-sehat/medication/" + nr + "/request",
        med_dispense: "/satu-sehat/medication/" + nr + "/dispense",
        med_statement: "/satu-sehat/medication/" + nr + "/statement",
        lab_request: "/satu-sehat/laboratory/" + nr + "/request",
        lab_specimen: "/satu-sehat/laboratory/" + nr + "/specimen",
        lab_observation: "/satu-sehat/laboratory/" + nr + "/observation",
        lab_diagnostic: "/satu-sehat/laboratory/" + nr + "/diagnostic",
        rad_request: "/satu-sehat/radiology/" + nr + "/request",
        rad_specimen: "/satu-sehat/radiology/" + nr + "/specimen",
        rad_observation: "/satu-sehat/radiology/" + nr + "/observation",
        rad_diagnostic: "/satu-sehat/radiology/" + nr + "/diagnostic"
    };
    return m[key] || null;
}

function parseFhirResponse(data) {
    if (!data || typeof data !== "object") return { success: false, id: null, error: "Invalid response" };
    if (data.id) return { success: true, id: data.id, error: null };
    if (data.resourceID) return { success: true, id: data.resourceID, error: null };
    if (data.entry && Array.isArray(data.entry)) {
        for (const e of data.entry) {
            if (e.response && e.response.resourceID) return { success: true, id: e.response.resourceID, error: null };
        }
    }
    if (data.issue) return { success: false, id: null, error: data.issue[0] ? (data.issue[0].diagnostics || JSON.stringify(data.issue[0])) : "Unknown error" };
    if (data.error) return { success: false, id: null, error: data.error };
    if (data.pesan) {
        if (data.pesan.toLowerCase().indexOf("gagal") >= 0) return { success: false, id: null, error: data.pesan };
        return { success: true, id: null, error: null };
    }
    return { success: false, id: null, error: "No resource ID in response" };
}

function updateProgress(idx, total, stats) {
    const pct = Math.round((idx / total) * 100);
    document.getElementById("progressText").textContent = "Memproses " + idx + " / " + total;
    document.getElementById("progressPct").textContent = pct + "%";
    document.getElementById("progressBar").style.width = pct + "%";
    document.getElementById("progressBar").textContent = pct + "%";
    document.getElementById("cntSuccess").textContent = stats.success;
    document.getElementById("cntPartial").textContent = stats.partial;
    document.getElementById("cntFailed").textContent = stats.failed;
    document.getElementById("cntSkipped").textContent = stats.skipped;
}

function renderResourceResults(body, resResults) {
    let html = "";
    for (const [key, r] of Object.entries(resResults)) {
        let icon = "⬜", detail = "";
        if (r.action === "sent") {
            if (r.success) { icon = "✅"; detail = r.id || "OK"; }
            else { icon = "❌"; detail = r.error || "Error"; }
        } else if (r.action === "sending") { icon = "⏳"; detail = "mengirim..."; }
        else if (r.action === "skip_exists") { icon = "🔵"; detail = "sudah ada: " + (r.id || "-"); }
        else if (r.action === "skip_nodata") { icon = "⬜"; detail = "tidak ada data"; }
        else if (r.action === "skip_no_encounter") { icon = "⛔"; detail = "encounter belum berhasil"; }
        html += \'<div class="res-line"><span class="res-icon">\' + icon + \'</span><span class="res-label">\' + esc(r.label || key) + \'</span><span class="res-detail">\' + esc(detail) + \'</span></div>\';
    }
    body.innerHTML = html;
}

function renderCards() {
    const container = document.getElementById("cards");
    container.innerHTML = "";
    ITEMS.forEach((item, i) => {
        const idx = i + 1;
        let statusClass = "st-ready";
        let badgeHtml = "";
        if (item.to_send > 0) {
            badgeHtml = \'<span class="badge badge-send">\' + item.to_send + \' kirim</span>\';
        }
        if (item.already_sent > 0) {
            badgeHtml += \'<span class="badge badge-done">\' + item.already_sent + \' sudah</span>\';
        }
        if (item.to_send === 0 && item.already_sent > 0) {
            statusClass = "st-complete";
            badgeHtml = \'<span class="badge badge-done">LENGKAP</span>\';
        }
        if (item.to_send === 0 && item.already_sent === 0) {
            statusClass = "st-skipped";
            badgeHtml = \'<span class="badge badge-nodata">NO DATA</span>\';
        }

        container.innerHTML += \'<div class="card \' + statusClass + \'" id="card-\' + idx + \'">\' +
            \'<div class="card-header" onclick="toggleCard(\' + idx + \')">\' +
                \'<div class="card-title">\' + idx + \'. \' + esc(item.no_rawat) + \' <small>\' + esc(item.nm_pasien) + \' &mdash; dr. \' + esc(item.nm_dokter) + \' (\' + item.status_lanjut + (item.tgl_registrasi ? \', \' + item.tgl_registrasi : \'\') + \')</small></div>\' +
                \'<div class="card-meta" id="meta-\' + idx + \'">\' + badgeHtml + \'</div>\' +
            \'</div>\' +
            \'<div class="card-body" id="body-\' + idx + \'">Klik untuk muat detail...</div>\' +
        \'</div>\';
    });
    document.getElementById("btnStart").disabled = ITEMS.length === 0;
}

async function toggleCard(idx) {
    const card = document.getElementById("card-" + idx);
    const body = document.getElementById("body-" + idx);
    if (card.classList.contains("open")) { card.classList.remove("open"); return; }
    card.classList.add("open");
    if (planCache[idx]) { body.innerHTML = renderPlan(planCache[idx]); return; }
    body.innerHTML = \'<div style="padding:6px;color:#6b7280;">⏳ Memuat detail...</div>\';
    const item = ITEMS[idx - 1];
    const force = document.getElementById("forceCheck").checked;
    try {
        const url = "/satu-sehat/batch-plan?no_rawat=" + encodeURIComponent(item.no_rawat)
            + "&status_lanjut=" + encodeURIComponent(item.status_lanjut)
            + (force ? "&force=1" : "");
        const resp = await fetch(url);
        const plan = await resp.json();
        planCache[idx] = plan;
        body.innerHTML = renderPlan(plan);
    } catch (e) {
        body.innerHTML = \'<div style="padding:6px;color:#ef4444;">❌ Gagal memuat: \' + esc(String(e)) + \'</div>\';
    }
}

function renderPlan(plan) {
    let html = "";
    for (const [key, p] of Object.entries(plan)) {
        let icon = "⬜", detail = "";
        if (p.action === "send") { icon = "📤"; detail = "akan dikirim"; }
        else if (p.action === "skip_exists") { icon = "🔵"; detail = "sudah ada: " + (p.id || "-"); }
        else if (p.action === "skip_nodata") { icon = "⬜"; detail = "tidak ada data"; }
        html += \'<div class="res-line"><span class="res-icon">\' + icon + \'</span><span class="res-label">\' + esc(p.label) + \'</span><span class="res-detail">\' + detail + \'</span></div>\';
    }
    return html;
}

function esc(s) {
    const d = document.createElement("div"); d.textContent = s || ""; return d.innerHTML;
}

// Navigate to load a different date
function loadData() {
    const dari = document.getElementById("inputDari").value;
    const sampai = document.getElementById("inputSampai").value;
    const f = document.getElementById("forceCheck").checked;
    let url = "/satu-sehat/batch-tanggal?tanggal_dari=" + encodeURIComponent(dari)
            + "&tanggal_sampai=" + encodeURIComponent(sampai);
    if (f) url += "&force=1";
    window.location.href = url;
}

// Start sending — calls each resource endpoint directly (no curl-to-self)
async function startSend() {
    if (running) return;
    running = true;
    stopped = false;
    document.getElementById("btnStart").disabled = true;
    document.getElementById("btnStop").style.display = "";
    document.getElementById("progressWrap").style.display = "";

    const force = document.getElementById("forceCheck").checked;
    const stats = { success: 0, partial: 0, failed: 0, skipped: 0 };
    const failedList = [];
    const total = ITEMS.length;

    for (let i = 0; i < total; i++) {
        if (stopped) break;

        const item = ITEMS[i];
        const idx = i + 1;
        const card = document.getElementById("card-" + idx);
        const meta = document.getElementById("meta-" + idx);
        const body = document.getElementById("body-" + idx);

        card.className = "card st-ready";
        meta.innerHTML = \'<span class="badge badge-processing">PROSES...</span>\';
        card.classList.add("open");
        card.scrollIntoView({ behavior: "smooth", block: "center" });

        // Step 1: Get sending plan
        let plan;
        if (planCache[idx]) {
            plan = planCache[idx];
        } else {
            try {
                await rateLimitWait();
                const planUrl = "/satu-sehat/batch-plan?no_rawat=" + encodeURIComponent(item.no_rawat)
                    + "&status_lanjut=" + encodeURIComponent(item.status_lanjut)
                    + (force ? "&force=1" : "");
                const planResp = await fetch(planUrl);
                plan = await planResp.json();
                planCache[idx] = plan;
            } catch (e) {
                card.className = "card st-failed";
                meta.innerHTML = \'<span class="badge badge-failed">GAGAL</span>\';
                body.innerHTML = \'<div class="res-line"><span class="res-icon">❌</span><span class="res-detail">\' + esc(String(e)) + \'</span></div>\';
                stats.failed++;
                failedList.push(item.no_rawat);
                updateProgress(idx, total, stats);
                continue;
            }
        }

        // Step 2: Send each resource directly (1 worker per call, no curl-to-self)
        const noRawatUrl = item.no_rawat.replace(/\\//g, "");
        let sentCount = 0, okCount = 0, errCount = 0;
        const resResults = {};
        let encounterFailed = false;

        for (const [key, p] of Object.entries(plan)) {
            if (stopped) break;

            if (p.action !== "send") {
                resResults[key] = p;
                continue;
            }

            if (encounterFailed && key !== "encounter") {
                resResults[key] = { label: p.label, action: "skip_no_encounter" };
                continue;
            }

            const url = buildResourceUrl(key, noRawatUrl);
            if (!url) {
                resResults[key] = { label: p.label, action: "sent", success: false, error: "Unknown: " + key };
                errCount++; sentCount++;
                continue;
            }

            // Show live progress
            resResults[key] = { label: p.label, action: "sending" };
            renderResourceResults(body, resResults);

            await rateLimitWait();

            try {
                const resp = await fetch(url);
                const text = await resp.text();
                let data = null;
                try { data = JSON.parse(text); } catch(_) {}
                const parsed = parseFhirResponse(data);
                sentCount++;
                if (parsed.success) {
                    okCount++;
                    resResults[key] = { label: p.label, action: "sent", success: true, id: parsed.id };
                } else {
                    errCount++;
                    resResults[key] = { label: p.label, action: "sent", success: false, error: parsed.error };
                    if (key === "encounter") encounterFailed = true;
                }
            } catch (e) {
                sentCount++; errCount++;
                resResults[key] = { label: p.label, action: "sent", success: false, error: String(e) };
                if (key === "encounter") encounterFailed = true;
            }

            renderResourceResults(body, resResults);
        }

        // Determine overall status
        let st = "skipped";
        if (sentCount > 0) {
            if (errCount === 0) st = "success";
            else if (okCount > 0) st = "partial";
            else st = "failed";
        }

        card.className = "card st-" + st;
        const labels = { success: "SUKSES", partial: "PARTIAL", failed: "GAGAL", skipped: "SKIP" };
        meta.innerHTML = \'<span class="badge badge-\' + st + \'">\' + (labels[st] || st.toUpperCase()) + \'</span>\';

        if (stats[st] !== undefined) stats[st]++;
        if (st === "failed" || st === "partial") failedList.push(item.no_rawat);
        updateProgress(idx, total, stats);
    }

    // Done
    running = false;
    document.getElementById("btnStop").style.display = "none";
    document.getElementById("btnStart").disabled = false;
    document.getElementById("btnStart").textContent = "▶ Kirim Ulang";

    if (stopped) {
        document.getElementById("progressText").textContent = "Dihentikan pada " + document.getElementById("progressText").textContent.split(" ").slice(1).join(" ");
    } else {
        document.getElementById("progressText").textContent = "Selesai — " + total + " no rawat diproses";
    }

    showSummary(stats, failedList, total);
}

function stopSend() {
    stopped = true;
    document.getElementById("btnStop").style.display = "none";
}

function showSummary(stats, failedList, total) {
    const div = document.getElementById("summaryDiv");
    let html = \'<h3>Ringkasan Pengiriman</h3>\' +
        \'<table>\' +
        \'<tr><th>Keterangan</th><th>Jumlah</th></tr>\' +
        \'<tr><td>Total No Rawat</td><td><strong>\' + total + \'</strong></td></tr>\' +
        \'<tr><td><span class="dot dot-green"></span> Sukses</td><td><strong>\' + stats.success + \'</strong></td></tr>\' +
        \'<tr><td><span class="dot dot-yellow"></span> Partial</td><td><strong>\' + stats.partial + \'</strong></td></tr>\' +
        \'<tr><td><span class="dot dot-red"></span> Gagal</td><td><strong>\' + stats.failed + \'</strong></td></tr>\' +
        \'<tr><td><span class="dot dot-gray"></span> Skip</td><td><strong>\' + stats.skipped + \'</strong></td></tr>\' +
        \'</table>\';

    if (failedList.length > 0) {
        html += \'<h3 style="margin-top:14px;">No Rawat Gagal / Partial</h3><ul style="font-size:.85rem;padding-left:20px;margin:6px 0;">\';
        failedList.forEach(function(nr) { html += "<li>" + esc(nr) + "</li>"; });
        html += "</ul>";
    }

    if (stats.failed === 0 && stats.partial === 0 && total > 0) {
        html += \'<div style="margin-top:14px;padding:12px;background:var(--green-bg);color:#166534;border-radius:8px;font-weight:600;">✅ Semua pengiriman sudah selesai!</div>\';
    }

    div.innerHTML = html;
    div.style.display = "";
    div.scrollIntoView({ behavior: "smooth" });
}

// Render on load
renderCards();
</script>

</body>
</html>';
    }

}
