<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing HTML block instances.
 *
 * @package   block_course_reports
 * @author    Manuel Quistial based in https://moodle.org/plugins/block_analytics_graphs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require('locallib.php');
require('javascriptfunctions.php');

$courseid = optional_param('courseid', null, PARAM_INT);
$legacy = required_param('legacy', PARAM_INT);
$startdate = optional_param('from', '***', PARAM_TEXT);

require_login();
$context = context_system::instance();

$course_report = get_string('pluginname', 'block_course_reports');
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/blocks/course_reports/view.php');

$course_data = get_course($courseid);

$courseparams = get_course($courseid);
if ($startdate === '***') {
	$startdate = $courseparams->startdate;
} else {
	$datetoarray = explode('-', $startdate);
	$starttime = new DateTime("now", core_date::get_server_timezone_object());
	$starttime->setDate((int)$datetoarray[0], (int)$datetoarray[1], (int)$datetoarray[2]);
	$starttime->setTime(0, 0, 0);
	$startdate = $starttime->getTimestamp();
}
$coursename = $courseparams->fullname;

$courseurl = new \moodle_url($CFG->wwwroot.'/course/view.php', ['id' => $courseid]);
$PAGE->navbar->add($course_data->shortname, $courseurl);
$PAGE->navbar->add($course_report);
$PAGE->set_title($course_report.': '.$coursename);
$PAGE->set_heading($coursename);

echo $OUTPUT->header();

$students = block_analytics_graphs_get_students($courseid);

$numberofstudents = count($students);
if ($numberofstudents == 0) {
    echo(get_string('no_students', 'block_course_reports'));
    exit;
}
foreach ($students as $tuple) {
        $arrayofstudents[] = array('userid' => $tuple->id ,
                                'nome' => $tuple->firstname.' '.$tuple->lastname,
                                'email' => $tuple->email);
}

/* Get the number of days with access by week */
$resultado = block_analytics_graphs_get_number_of_days_access_by_week($courseid, $students, $startdate, $legacy); // A

/* Get the students that have no access */
$maxnumberofweeks = 0;
foreach ($resultado as $tuple) {
    $arrayofaccess[] = array('userid' => $tuple->userid ,
                            'nome' => $tuple->firstname.' '.$tuple->lastname,
                            'email' => $tuple->email);
    if ($tuple->week > $maxnumberofweeks) {
        $maxnumberofweeks = $tuple->week;
    }
}

/* Get the number of modules accessed by week */
$accessresults = block_analytics_graphs_get_number_of_modules_access_by_week($courseid, $students, $startdate, $legacy); // B
$maxnumberofresources = 0;
foreach ($accessresults as $tuple) {
    if ( $tuple->number > $maxnumberofresources) {
        $maxnumberofresources = $tuple->number;
    }
}

/* Get the total number of modules accessed */
$numberofresourcesresult = block_analytics_graphs_get_number_of_modules_accessed($courseid, $students, $startdate, $legacy);

/* Convert results to javascript */
$resultado = json_encode($resultado);
$accessresults = json_encode($accessresults);
$numberofresourcesresult = json_encode($numberofresourcesresult);

?>


<html>
<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?php echo get_string('hits_distribution', 'block_course_reports'); ?></title>

<link rel="stylesheet" href="externalref/jquery-ui-1.12.1/jquery-ui.css">
<script src="externalref/jquery-1.12.2.js"></script> 
<script src="externalref/jquery-ui-1.12.1/jquery-ui.js"></script>
<script src="externalref/highstock.js"></script>
<script src="externalref/no-data-to-display.js"></script>
<script src="externalref/exporting.js"></script>
<script src="externalref/export-csv-master/export-csv.js"></script> 

<style>
    div.res_query {
        display:table;
        margin-right:auto;
        margin-left:auto;
    }
    .chart {
        float: left;
        display: block;
        margin: auto;
    }
    .ui-dialog {
        position: fixed;
    }
    #result {
        text-align: right;
        color: gray;
        min-height: 2em;
    }
    #table-sparkline {
        border: 1px solid #c3c3c3;
        margin: 0 auto;
        margin-top: 10px;
        border-collapse: collapse;
    }
    div.student_panel{
        font-size: 0.85em;
        min-height: 450px;
        margin-left: auto;
        margin-right: auto;
    }
    a.contentaccess, a.submassign, a.msgs, a.mail, a.quizchart, a.forumchart{
        font-size: 0.85em;
    }
    table.res_query {
        font-size: 0.85em;
    }
    .image-exclamation {
        width: 25px;
        height: 20px;
        vertical-align: middle;
        visibility: hidden;
    }
    .warnings {
        float: right;
        align: right;
        margin-left: 10px;
        display: inline-flex;
        flex-direction: row;
        justify-content: space-around;
        width: 55px;
    }
    .warning1, .warning2 {
        width: 25px;
    }
    .warning1 {
        order: 1;
        margin-right: 5px;
    }
    .warning2 {
        order: 2;
    }
    th {
        font-weight: bold;
        text-align: left;
    }
    td, th {
        padding: 5px;
        border-top: 1px solid silver;
        border-bottom: 1px solid silver;
        border-right: 1px solid silver;
        height: 60px;
    }

    .highcharts-container {
        overflow: visible !important;
    }
    .highcharts-tooltip {
        pointer-events: all !important;
    }
    .highcharts-tooltip>span {
        background: white;
        border: 1px solid silver;
        border-radius: 3px;
        box-shadow: 1px 1px 2px #888;
        padding: 8px;
        max-height: 250px;
        width: auto;
        overflow: auto;
    }
    .scrollableHighchartsTooltipAddition {
        position: relative;
        z-index: 50;
        border: 2px solid rgb(0, 108, 169);
        border-radius: 5px;
        background-color: #ffffff;
        padding: 5px;
        font-size: 9pt;
        overflow: auto;
        height: 200px;
    }
    .totalgraph {
        width: 55%;
        display: block;
        margin-left: auto;
        margin-right: auto;
        margin-top: 50px;
        border-radius: 0px;
        padding: 10px;
        border-top: 1px solid silver;
        border-bottom: 1px solid silver;
        border-right: 1px solid silver;
    }

    .ui-dialog{
        z-index: 1030;
    }
</style>


<script type="text/javascript">
    var courseid = <?php echo json_encode($courseid); ?>;
    var coursename = <?php echo json_encode($coursename); ?>;
    var geral = <?php echo $resultado; ?>;
    var moduleaccess = <?php echo $accessresults; ?>;
    var numberofresources = <?php echo $numberofresourcesresult; ?>;
    var legacy = <?php echo json_encode($legacy); ?>;
    var weekBeginningOffset = 1; //added to each loop making charts start from WEEK#1 instead of WEEK#0
    var nomes = [];

    $.each(geral, function(ind, val){   
        var nome = val.firstname+" "+val.lastname;
        if (nomes.indexOf(nome) === -1)
            nomes.push(nome);

    });
    
    nomes.sort();
    var students = [];
    
    // Organize data to generate right graph.
    $.each(geral, function(ind, val){
        if (students[val.userid]){
            var student = students[val.userid];
            student.semanas[val.week] = Number(val.week);
            student.acessos[val.week] = Number(val.number);
            student.totalofaccesses += Number(val.number);
            student.pageViews += Number(val.numberofpageviews);
            students[val.userid] = student;
        }else{
            // Nessa parte criamos um obj que contera um array com a semana (indice) e outro com o number (valor)
            // os dois tendo a mesma chave que ÃÂ© o numero dasemana.
            var student = {};
            student.userid = Number(val.userid);
            student.nome = val.firstname+" "+val.lastname;
            student.email = val.email;
            student.semanas = [];
            student.semanas[val.week] = Number(val.week);
            student.acessos = [];
            student.acessos[val.week] = Number(val.number);
            student.totalofaccesses = Number(val.number);
            student.pageViews = Number(val.numberofpageviews);
            if (numberofresources[val.userid])
                student.totalofresources = numberofresources[val.userid].number ;
            else
                student.totalofresources = 0;
            students[val.userid] = student;
        }
    });

    $.each(moduleaccess, function(index, value){
        if (students[value.userid]){
            var student = students[value.userid];
            if (student.semanasModulos === undefined)
                student.semanasModulos = [];                
            student.semanasModulos[value.week] = Number(value.week);
            if (student.acessosModulos === undefined)
                student.acessosModulos = [];
            student.acessosModulos[value.week] = (value.number>0 ? Number(value.number) : 0 );
            students[value.userid] = student;
        }
    });

    function trata_array(array){
        var novo = [];
        $.each(array, function(ind, value){
            if (!value)
                novo[ind] = 0;
            else
                novo[ind] = value;
        });
        if (novo.length <= <?php echo $maxnumberofweeks; ?>) {
            novo = pan_array_to_max_number_of_weeks(novo);
        }
        return novo;
    }

    function pan_array_to_max_number_of_weeks(array) {
        for (i = array.length; i <= (<?php echo $maxnumberofweeks; ?>); i++ ) {
        if (array[i] === undefined)
            array[i] = 0;
        }
        return array;
    }

    function gerar_grafico_modulos(student){
        if (student.acessosModulos !== undefined){
                $("#modulos-"+student.userid).highcharts({

                chart: {
                        borderWidth: 0,
                        type: 'area',
                        margin: [0, 0, 0, 0],
                        spacingBottom: 0,
                        width: 250,
                        height: 60,
                        style: {
                                overflow: 'visible'
                        },
                        skipClone: true,
                },

                xAxis: {
                        labels: {
                                enabled: false
                        },
                        title: {
                                text: null
                        },
                        startOnTick: false,
                        endOnTick: false,
                        tickPositions: [],
                        tickInterval: 1,
                        minTickInterval: 24,
                        min: (<?php echo $maxnumberofweeks; ?> + weekBeginningOffset) - 15,
                        max: <?php echo $maxnumberofweeks; ?> + weekBeginningOffset
                 },

                navigator: {
                    enabled: false,
                    margin: 5
                },

                scrollbar: {
                    enabled: true,
                    height: 10
                },

                yAxis: {
                        minorTickInterval: 5,
                        endOnTick: false,
                        startOnTick: false,
                        labels: {
                                enabled: false
                        },
                        title: {
                                text: null
                        },
                        tickPositions: [0],
                        max: <?php echo $maxnumberofresources;?>,
                        tickInterval: 5
                },

                title: {
                        text: null
                },

                credits: {
                          enabled: false
                 },

                legend: {
                        enabled: false
                },

                tooltip: {
                        backgroundColor: null,
                        borderWidth: 0,
                        shadow: false,
                        useHTML: true,
                        hideDelay: 0,
                        shared: true,
                        padding: 0,
                        headerFormat: '',
                        pointFormat: <?php echo "'".get_string('week_number', 'block_course_reports').": '"; ?> +
                                        '{point.x}<br>' +  
                                        <?php echo "'".get_string('resources_with_access', 'block_course_reports').": '"; ?> +
                                        '{point.y}',
                        positioner: function (w, h, point) { return { x: point.plotX - w / 2, y: point.plotY - h};}
                },
                plotOptions: {
                        series: {
                                animation:  { 
                                        duration: 4000
                                },
                                lineWidth: 1,
                                shadow: false,
                                states: {
                                        hover: {
                                                lineWidth: 1
                                                }
                                        },
                                marker: {
                                        radius: 2,
                                        states: {
                                                hover: {
                                                        radius: 4
                                                        }
                                                }                                        },
                                fillOpacity: 0.25
                        },
                },
                series: [{
                    pointStart: weekBeginningOffset,
                    data: trata_array(student.acessosModulos)
                }],
                
                
                exporting: {
                    enabled: false
                },
                
                    });                    
                    last_week = <?php echo $maxnumberofweeks; ?>;
                    if(!(last_week in student.acessosModulos)){
                        $("#" + student.userid + "-1-img").css("visibility", "visible");
                    }
        }else{
                $("#" + student.userid + "-2-img").css("visibility", "visible");
                // $("#modulos-"+student.userid).text("Este usuário não acessou nenhum material ainda.");
                // $("#modulos-"+student.userid).text(":(");
        }
    }

    function gerar_grafico(student){
        $("#acessos-"+student.userid).highcharts({

            chart: {
                borderWidth: 0,
                type: 'area',
                margin: [0, 0, 0, 0],
                spacingBottom: 0,
                width: 250,
                height: 60,
                style: {
                    overflow: 'visible'
                },
                skipClone: true,
            },


            xAxis: {
                labels: {
                    enabled: false
                },
                title: {
                    text: null
                },
                startOnTick: false,
                endOnTick: false,
                tickPositions: [],
                tickInterval: 1,
                minTickInterval: 24,
                min: (<?php echo $maxnumberofweeks; ?> + weekBeginningOffset) - 15,
                max: <?php echo $maxnumberofweeks; ?>  + weekBeginningOffset
            },

            navigator: {
                enabled: false,
                margin: 5
            },

            scrollbar: {
                enabled: true,
                height: 10
            },

            yAxis: {
                minorTickInterval: 1,
                endOnTick: false,
                startOnTick: false,
                labels: {
                    enabled: false
                },
                title: {
                    text: null
                },
                tickPositions: [0],
                max: 7, 
                tickInterval: 1
            },


            title: {
                text: null
            },


            credits: {
                enabled: false
            },


            legend: {
                enabled: false
            },


            tooltip: {
                backgroundColor: null,
                borderWidth: 0,
                shadow: false,
                useHTML: true,
                hideDelay: 0,
                shared: true,
                padding: 0,
                headerFormat: '',
                pointFormat: <?php echo "'".get_string('week_number', 'block_course_reports').": '"; ?> +
                    '{point.x}<br>' +
                <?php echo "'".get_string('days_with_access', 'block_course_reports').": '"; ?> +
                    '{point.y}',
                positioner: function (w, h, point) { return { x: point.plotX - w / 2, y: point.plotY - h}; }
            },


            plotOptions: {
                series: {
                    animation: {
                        duration: 2000
                    },
                    lineWidth: 1,
                    shadow: false,
                    states: {
                        hover: {
                            lineWidth: 1
                        }
                    },
                    marker: {
                        radius: 2,
                        states: {
                            hover: {
                                radius: 4                                                        }
                        }
                    },
                    fillOpacity: 0.25
                },
            },


            series: [{
                pointStart: weekBeginningOffset,
                data: trata_array(student.acessos)
            }],


            exporting: {
                enabled: false
            },

        });
    }

    function generalRow(value, red_excl, yellow_excl, red_tooltip, yellow_tooltip){
        var linha = "<tr id='tr-student-"+value.userid+
            "'>\
            <td><input type='checkbox' class='checkbox_email' name='correo[]' id="+value.userid+"></td> \
            <th><span class='nome_student' style='cursor:hand'\
            id='linha-"+value.userid+"'>"+value.nome+"</span>"+
                "<div class='warnings'>\
                    <div class='warning1' id='"+value.userid+"_1'>\
                        <img\
                            src='" + red_excl + "'\
                            title='" + red_tooltip + "'\
                            class='image-exclamation'\
                            id='" + value.userid + "-1-img'\
                        >\
                    </div>\
                    <div class='warning2' id='"+value.userid+"_2'>\
                        <img\
                            src='" + yellow_excl + "'\
                            title='" + yellow_tooltip +"'\
                            class='image-exclamation'\
                            id='" + value.userid + "-2-img'\
                        >\
                    </div>\
                </div></th>" +
                "<td>"+
                        value.pageViews+
                "</td>"+
                "<td>"+
                        value.totalofaccesses+
                "</td>"+
                "<td width='250' id='acessos-"+value.userid+"'>"+
                "</td>"+
                "<td>"+                                                
                //(value.totalModulos>0? value.totalModulos : 0)+
                (numberofresources[value.userid]? numberofresources[value.userid].number : 0)+
                "</td>"+
                "<td id='modulos-"+value.userid+"'>"+
                "</td>"+
        "</tr>";
        $("table").append(linha);
        gerar_grafico(value);
        gerar_grafico_modulos(value);
    }

    function createRow(array, nomes, risk){
        var red_excl = "images/warning-attention-road-sign-exclamation-mark.png";
        var yellow_excl = "images/exclamation_sign.png";
        var red_tooltip = <?php echo json_encode(get_string('red_tooltip', 'block_course_reports')); ?>;
        var yellow_tooltip = <?php echo json_encode(get_string('yellow_tooltip', 'block_course_reports')); ?>;
        $.each(nomes, function(ind,val){
            var nome = val;
            $.each(array, function(index, value){
                if (value){
                    if(risk == 1){
                        if (value.acessosModulos !== undefined){
                            generalRow(value, red_excl, yellow_excl, red_tooltip, yellow_tooltip)
                        }
                    }
                    else if(risk == 2){
                        if (value.acessosModulos == undefined){
                            generalRow(value, red_excl, yellow_excl, red_tooltip, yellow_tooltip)
                        }
                    }
                    else{
                        generalRow(value, red_excl, yellow_excl, red_tooltip, yellow_tooltip)
                    }
                }
            });
        });
    }
    
</script>

</head>
<body>

    <div>
        <h4><?php  echo  get_string('begin_date', 'block_course_reports') . ": " . userdate($startdate, get_string('strftimerecentfull'));?> </h4>
    </div>

    <div id="select-risk">
        <label for="risk" style="margin-right:10px;"><?php  echo get_string('select_risk', 'block_course_reports');?> </label>
        <select id="risk" name="risk">
            <?php 
                $all_risks =  get_string('all_risk', 'block_course_reports');
                echo $all_risks;
                foreach ($all_risks as $key => $value) { 
            ?>
                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
            <?php
            }
            ?>
        </select>
        <button type="button" class="button-fancy" id="generate_email" style="float:right;" disabled><?php echo get_string('send_email', 'block_course_reports');?></button>
    </div>

    <table id="table-sparkline">
        <thead>
            <tr>         
                <th><input type='checkbox' name='correo[]' id="correos"></th> 
                <th><center><?php echo get_string('students', 'block_course_reports');?></center></th>                
                <th width=50><center><?php echo get_string('hits', 'block_course_reports');?></center></th>                
                <th width=50><center><?php echo get_string('days_with_access', 'block_course_reports');?></center></th>                
                <th><center><?php echo get_string('days_by_week', 'block_course_reports');
                    echo "<br><i>(". get_string('number_of_weeks', 'block_course_reports')
                            . ": " . ($maxnumberofweeks + 1).")</i>";?></center></th>
                <th width=50><center><?php echo get_string('resources_with_access', 'block_course_reports');?></center></th>                
                <th><center><?php echo get_string('resources_by_week', 'block_course_reports');?></center></th>
            </tr>
        </thead>
        <tbody id='tbody-sparklines'>
            <script type="text/javascript">
                const risk = localStorage.getItem('risk') || 0
                $('#risk').val(risk);
                createRow(students, nomes, risk);            
            </script>
        </tbody>
    </table>

    <script type="text/javascript">

        var checkboxes = ""
        var div = 
                "<div class='div_nomes' id='modal_email' title='Email Report'>" +
                    "<div class='student_tabs'>" +
                        "<div class='email_panel' id='email_panel_id'>"
                        "</div>"
                    "</div>" + 
                "</div>"; 
        document.write(div);    

        $( "#risk" ).change(function() {
            const risk_choice = $( "#risk" ).val()
            localStorage.setItem('risk', risk_choice)  
            location.reload();
        });

        sendEmail();
        
        $("#generate_email").bind("click", function(){          
            $("#modal_email").dialog("option", "width", 1000);
            $("#modal_email").dialog("option", "height", 600);
            var offsetTop = window.innerHeight/2 - $("#modal_email").dialog("option", "height")/2;
            $("#modal_email").dialog("option", "position", {
                my:"center top",
                at:"center top+" + offsetTop,
                of:window
            });
            $("#modal_email").dialog("open");
            $("#email_panel_id").html(createEmailForm('Email Report', students ,checkboxes, courseid, 'hits.php',
                            <?php echo json_encode(get_string('info_coursetype', 'block_course_reports') . ': ' . block_analytics_graphs_get_course_name($courseid)); ?>) + "</div>")
        });

        $("#correos").click(function(event){
            checkboxes = $(`.checkbox_email`)
            if(event.target.checked){
                $("#generate_email").prop('disabled', false)
                checkboxes.each(function(){
                    if(!$(this).is(':disabled')){
                        $(this).prop('checked', true);
                    }
                });
            }else{
                $("#generate_email").prop('disabled', true)
                checkboxes.each(function(){
                    if(!$(this).is(':disabled')){
                        $(this).prop('checked', false);
                    }
                });
            }
        });

        $(".checkbox_email").click(function(event){
            checkboxes = $(`.checkbox_email`)
            if(event.target.checked){
                $("#generate_email").prop('disabled', false)
            }else{
                $("#generate_email").prop('disabled', true)
            }
        });

    </script>
</body>
</html>
