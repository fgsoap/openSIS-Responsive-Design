<?php

#**************************************************************************
#  openSIS is a free student information system for public and non-public 
#  schools from Open Solutions for Education, Inc. web: www.os4ed.com
#
#  openSIS is  web-based, open source, and comes packed with features that 
#  include student demographic info, scheduling, grade book, attendance, 
#  report cards, eligibility, transcripts, parent portal, 
#  student portal and more.   
#
#  Visit the openSIS web site at http://www.opensis.com to learn more.
#  If you have question regarding this system or the license, please send 
#  an email to info@os4ed.com.
#
#  This program is released under the terms of the GNU General Public License as  
#  published by the Free Software Foundation, version 2 of the License. 
#  See license.txt.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#***************************************************************************************
include('../../RedirectModulesInc.php');
include '_makeLetterGrade.fnc.php';
$course_period_id = UserCoursePeriod();
$course_id = DBGet(DBQuery('SELECT cp.COURSE_ID,c.TITLE FROM course_periods cp,courses c WHERE c.COURSE_ID=cp.COURSE_ID AND cp.COURSE_PERIOD_ID=\'' . $course_period_id . '\''));
$course_title = $course_id[1]['TITLE'];
$course_id = $course_id[1]['COURSE_ID'];
##########################################################################
#####################################################################################
if ($_REQUEST['modfunc'] == 'save') {
    $config_RET = DBGet(DBQuery('SELECT TITLE,VALUE FROM program_user_config WHERE USER_ID=\'' . User('STAFF_ID') . '\' AND PROGRAM=\'Gradebook\' AND VALUE LIKE \'%_' . UserCoursePeriod() . '\' '), array(), array('TITLE'));
    if (count($config_RET))
        foreach ($config_RET as $title => $value) {
            $unused_var = explode('_', $value[1]['VALUE']);
            $program_config[User('STAFF_ID')][$title] = $unused_var[0];
//				$program_config[$staff_id][$title] = rtrim($value[1]['VALUE'],'_'.$course_period_id);
        } else
        $program_config[User('STAFF_ID')] = true;

    if (count($_REQUEST['st_arr'])) {
        $st_list = '\'' . implode('\',\'', $_REQUEST['st_arr']) . '\'';
        $extra['SELECT'] = ',ssm.START_DATE';
        $extra['WHERE'] = ' AND s.STUDENT_ID IN (' . $st_list . ')';
        Widgets('mailing_labels');

        $RET = GetStuList($extra);

        if (count($RET)) {
            $columns = array('ASSIGN_TYP' => 'Assignment Type', 'TITLE' => 'Assignment', 'ASSIGN_TYP_WG' => 'Weight (%)');
            if ($_REQUEST['assigned_date'] == 'Y')
                $columns += array('ASSIGNED_DATE' => 'Assigned Date');
            if ($_REQUEST['due_date'] == 'Y')
                $columns += array('DUE_DATE' => 'Due Date');
            $columns += array('POINTS' => 'Points', 'LETTER_GRADE' => 'Grade', 'WEIGHT_GRADE' => 'Weighted Grade', 'COMMENT' => 'Comment');

            $handle = PDFStart();
            foreach ($RET as $student) {
                $student_points = $total_points = $percent_weights = array();
                $tot_weighted_percent = array();
                $assignment_type_count = array();

                unset($_openSIS['DrawHeader']);
                echo "<table width=100%  style=\" font-family:Arial; font-size:12px;\" >";
                echo "<tr><td width=105>" . DrawLogo() . "</td><td  style=\"font-size:15px; font-weight:bold; padding-top:20px;\">" . GetSchool(UserSchool()) . "<div style=\"font-size:12px;\">Student Progress Report</div></td><td align=right style=\"padding-top:20px;\">" . ProperDate(DBDate()) . "<br/>Powered by openSIS</td></tr><tr><td colspan=3 style=\"border-top:1px solid #333;\">&nbsp;</td></tr></table>";
                echo '<table border=0 style=\"font-size:12px;\">';
                echo "<tr><td>Student Name:</td>";
                echo "<td>" . $student['FULL_NAME'] . "</td></tr>";
                echo "<tr><td>ID:</td>";
                echo "<td>" . $student['STUDENT_ID'] . " </td></tr>";
                echo "<tr><td>Grade:</td>";
                echo "<td>" . $student['GRADE_ID'] . " </td></tr>";
                echo "<tr><td>Course</td><td>" . $course_title . "</td></tr>";
                echo "<tr><td>Marking Period:</td>";
                echo "<td>" . GetMP(UserMP()) . " </td></tr>";


                if ($_REQUEST['mailing_labels'] == 'Y')
                    echo '<tr><TD colspan=2>' . $student['MAILING_LABEL'] . '</TD></TR>';

                unset($student_points);
                unset($total_points);
                unset($percent_weights);
                unset($total_stpoints);
                unset($total_asgnpoints);
                if ($program_config[User('STAFF_ID')]['WEIGHT'] == 'Y') {
                    $course_periods = DBGet(DBQuery('select marking_period_id from course_periods where course_period_id=' . UserCoursePeriod()));
                    if ($course_periods[1]['MARKING_PERIOD_ID'] == NULL) {
                        $school_years = DBGet(DBQuery('select marking_period_id from  school_years where  syear=' . UserSyear() . ' and school_id=' . UserSchool()));
                        $fy_mp_id = $school_years[1]['MARKING_PERIOD_ID'];
                        $sql = 'SELECT a.TITLE,t.TITLE AS ASSIGN_TYP,a.ASSIGNED_DATE,a.DUE_DATE, t.ASSIGNMENT_TYPE_ID, t.FINAL_GRADE_PERCENT AS WEIGHT_GRADE  ,  t.FINAL_GRADE_PERCENT,t.FINAL_GRADE_PERCENT as ASSIGN_TYP_WG,g.POINTS,a.POINTS AS TOTAL_POINTS,g.COMMENT,g.POINTS AS LETTER_GRADE,g.POINTS AS LETTERWTD_GRADE,CASE WHEN (a.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=a.ASSIGNED_DATE) AND (a.DUE_DATE IS NULL OR CURRENT_DATE>=a.DUE_DATE) THEN \'Y\' ELSE NULL END AS DUE FROM gradebook_assignment_types t,gradebook_assignments a LEFT OUTER JOIN gradebook_grades g ON (a.ASSIGNMENT_ID=g.ASSIGNMENT_ID AND g.STUDENT_ID=\'' . $student['STUDENT_ID'] . '\' AND g.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\') WHERE   a.ASSIGNMENT_TYPE_ID=t.ASSIGNMENT_TYPE_ID AND (a.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\' OR a.COURSE_ID=\'' . $course_id . '\' AND a.STAFF_ID=\'' . User('STAFF_ID') . '\') AND t.COURSE_ID=\'' . $course_id . '\' AND (a.MARKING_PERIOD_ID=\'' . UserMP() . '\' OR a.MARKING_PERIOD_ID=\'' . $fy_mp_id . '\')';
                    } else {
                        $sql = 'SELECT a.TITLE,t.TITLE AS ASSIGN_TYP,a.ASSIGNED_DATE,a.DUE_DATE,  t.ASSIGNMENT_TYPE_ID,   t.FINAL_GRADE_PERCENT AS WEIGHT_GRADE  , t.FINAL_GRADE_PERCENT,t.FINAL_GRADE_PERCENT as ASSIGN_TYP_WG,g.POINTS,a.POINTS AS TOTAL_POINTS,g.COMMENT,g.POINTS AS LETTER_GRADE,g.POINTS AS LETTERWTD_GRADE,CASE WHEN (a.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=a.ASSIGNED_DATE) AND (a.DUE_DATE IS NULL OR CURRENT_DATE>=a.DUE_DATE) THEN \'Y\' ELSE NULL END AS DUE FROM gradebook_assignment_types t,gradebook_assignments a LEFT OUTER JOIN gradebook_grades g ON (a.ASSIGNMENT_ID=g.ASSIGNMENT_ID AND g.STUDENT_ID=\'' . $student['STUDENT_ID'] . '\' AND g.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\') WHERE   a.ASSIGNMENT_TYPE_ID=t.ASSIGNMENT_TYPE_ID AND (a.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\' OR a.COURSE_ID=\'' . $course_id . '\' AND a.STAFF_ID=\'' . User('STAFF_ID') . '\') AND t.COURSE_ID=\'' . $course_id . '\' AND a.MARKING_PERIOD_ID=\'' . UserMP() . '\'';
                    }
                } else {
                    $course_periods = DBGet(DBQuery('select marking_period_id from course_periods where course_period_id=' . UserCoursePeriod()));
                    if ($course_periods[1]['MARKING_PERIOD_ID'] == NULL) {
                        $school_years = DBGet(DBQuery('select marking_period_id from  school_years where  syear=' . UserSyear() . ' and school_id=' . UserSchool()));
                        $fy_mp_id = $school_years[1]['MARKING_PERIOD_ID'];
                        $sql = 'SELECT a.TITLE,t.TITLE AS ASSIGN_TYP,a.ASSIGNED_DATE,a.DUE_DATE,\'-1\' AS ASSIGNMENT_TYPE_ID,\'1\' AS FINAL_GRADE_PERCENT,\'N/A\' as WEIGHT_GRADE,\'N/A\' as ASSIGN_TYP_WG,g.POINTS,a.POINTS AS TOTAL_POINTS,g.COMMENT,g.POINTS AS LETTER_GRADE,g.POINTS AS LETTERWTD_GRADE,CASE WHEN (a.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=a.ASSIGNED_DATE) AND (a.DUE_DATE IS NULL OR CURRENT_DATE>=a.DUE_DATE) THEN \'Y\' ELSE NULL END AS DUE FROM gradebook_assignment_types t,gradebook_assignments a LEFT OUTER JOIN gradebook_grades g ON (a.ASSIGNMENT_ID=g.ASSIGNMENT_ID AND g.STUDENT_ID=\'' . $student[STUDENT_ID] . '\' AND g.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\') WHERE  a.ASSIGNMENT_TYPE_ID=t.ASSIGNMENT_TYPE_ID AND (a.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\' OR a.COURSE_ID=\'' . $course_id . '\' AND a.STAFF_ID=\'' . User('STAFF_ID') . '\')  AND t.COURSE_ID=\'' . $course_id . '\' AND (a.MARKING_PERIOD_ID=\'' . UserMP() . '\' OR a.MARKING_PERIOD_ID=\'' . $fy_mp_id . '\')';
                    } else {
                        $sql = 'SELECT a.TITLE,t.TITLE AS ASSIGN_TYP,a.ASSIGNED_DATE,a.DUE_DATE,\'-1\' AS ASSIGNMENT_TYPE_ID,\'1\' AS FINAL_GRADE_PERCENT,\'N/A\' as WEIGHT_GRADE,\'N/A\' as ASSIGN_TYP_WG,g.POINTS,a.POINTS AS TOTAL_POINTS,g.COMMENT,g.POINTS AS LETTER_GRADE,g.POINTS AS LETTERWTD_GRADE,CASE WHEN (a.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=a.ASSIGNED_DATE) AND (a.DUE_DATE IS NULL OR CURRENT_DATE>=a.DUE_DATE) THEN \'Y\' ELSE NULL END AS DUE FROM gradebook_assignment_types t,gradebook_assignments a LEFT OUTER JOIN gradebook_grades g ON (a.ASSIGNMENT_ID=g.ASSIGNMENT_ID AND g.STUDENT_ID=\'' . $student[STUDENT_ID] . '\' AND g.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\') WHERE  a.ASSIGNMENT_TYPE_ID=t.ASSIGNMENT_TYPE_ID AND (a.COURSE_PERIOD_ID=\'' . UserCoursePeriod() . '\' OR a.COURSE_ID=\'' . $course_id . '\' AND a.STAFF_ID=\'' . User('STAFF_ID') . '\')  AND t.COURSE_ID=\'' . $course_id . '\' AND a.MARKING_PERIOD_ID=\'' . UserMP() . '\'';
                    }
                }
                if ($_REQUEST['exclude_notdue'] == 'Y')
                    $sql .= ' AND ((a.ASSIGNED_DATE IS NULL OR CURRENT_DATE>=a.ASSIGNED_DATE) AND (a.DUE_DATE IS NULL OR CURRENT_DATE>=DUE_DATE) OR g.POINTS IS NOT NULL)';
                if ($_REQUEST['exclude_ec'] == 'Y')
                    $sql .= ' AND (a.POINTS!=\'0\' OR g.POINTS IS NOT NULL AND g.POINTS!=\'-1\')';
                $sql .= ' AND a.DUE_DATE>=\'' . $student[START_DATE] . '\' ORDER BY a.ASSIGNMENT_ID';
                $grades_RET = DBGet(DBQuery($sql), array('ASSIGNED_DATE' => '_removeSpaces', 'ASSIGN_TYP_WG' => '_makeAssnWG', 'DUE_DATE' => '_removeSpaces', 'TITLE' => '_removeSpaces', 'POINTS' => '_makeExtra', 'LETTER_GRADE' => '_makeExtra', 'WEIGHT_GRADE' => '_makeWtg'));
                $sum_points = $sum_percent = 0;
                $flag = false;
                if (count($percent_weights)) {
                    foreach ($percent_weights as $assignment_type_id => $percent) {
                        $flag = true;
//                                    $sum_points       += $student_points[$assignment_type_id] * $percent_weights[$assignment_type_id] / $total_points[$assignment_type_id];
//                                    $sum_percent      += $percent;
                        $total_stpoints += $student_points[$assignment_type_id];
                        $total_asgnpoints += $total_points[$assignment_type_id];
                    }
                }

//                        if($sum_percent>0)
//				$sum_points = $sum_points;
//			else
//				$sum_points = 0;
                if ($program_config[User('STAFF_ID')]['WEIGHT'] == 'Y') {
                    $assign_typ_wg = array();
                    $tot_weight_grade = '';
//                           $sum_points=0;
//                           print_r($grades_RET);
                    if (count($grades_RET)) {
                        foreach ($grades_RET as $key => $val) {
                            if ($val['LETTERWTD_GRADE'] != -1.00 && $val['LETTERWTD_GRADE'] != '') {
                                $wper = explode('%', $val['LETTER_GRADE']);
                                if ($tot_weighted_percent[$val['ASSIGNMENT_TYPE_ID']] != '')
                                    $tot_weighted_percent[$val['ASSIGNMENT_TYPE_ID']] = $tot_weighted_percent[$val['ASSIGNMENT_TYPE_ID']] + $wper[0];
                                else
                                    $tot_weighted_percent[$val['ASSIGNMENT_TYPE_ID']] = $wper[0];
                                if ($assignment_type_count[$val['ASSIGNMENT_TYPE_ID']] != '')
                                    $assignment_type_count[$val['ASSIGNMENT_TYPE_ID']] = $assignment_type_count[$val['ASSIGNMENT_TYPE_ID']] + 1;
                                else
                                    $assignment_type_count[$val['ASSIGNMENT_TYPE_ID']] = 1;
                                if ($val['ASSIGN_TYP_WG'] != '')
                                    $assign_typ_wg[$val['ASSIGNMENT_TYPE_ID']] = substr($val['ASSIGN_TYP_WG'], 0, -2);
                            }
                        }
                        $total_weightage = 0;
                        foreach ($assignment_type_count as $assign_key => $value) {
                            $total_weightage = $total_weightage + $assign_typ_wg[$assign_key];
                            if ($tot_weight_grade == '')
                                $tot_weight_grade = round((round(($tot_weighted_percent[$assign_key] / $value), 2) * $assign_typ_wg[$assign_key]) / 100, 2);
                            else
                                $tot_weight_grade = $tot_weight_grade + (round((round(($tot_weighted_percent[$assign_key] / $value), 2) * $assign_typ_wg[$assign_key]) / 100, 2));
                        }

                        $tot_weight_grade = $tot_weight_grade / 100;
                    }
                }
                $tot_weight_grade = ($tot_weight_grade / $total_weightage) * 100;
                if ($flag) {
//                            $link['add']['html'] = array('TITLE'=>'<B>Total</B>','LETTER_GRADE'=>'( '.$total_stpoints.' / '.$total_asgnpoints.' ) '._makeLetterGrade(($total_stpoints/$total_asgnpoints),"",User('STAFF_ID'),"%").'%&nbsp;'._makeLetterGrade($total_stpoints/$total_asgnpoints,"",User('STAFF_ID')),'WEIGHT_GRADE'=>$programconfig[User('STAFF_ID')]['WEIGHT']=='Y'?_makeLetterGrade($tot_weight_grade,"",User('STAFF_ID'),'%').'%&nbsp;'._makeLetterGrade($tot_weight_grade,"",User('STAFF_ID')):'N/A');
                    $link['add']['html'] = array('TITLE' => '<font style="font-size:13;font-weight:bold;"><B>Total</B></font>', 'POINTS' => '<font style="font-size:13;font-weight:bold;">' . $total_stpoints . ' / ' . $total_asgnpoints . '</font>', 'LETTER_GRADE' => '<font style="font-size:13;font-weight:bold;">' . _makeLetterGrade(($total_stpoints / $total_asgnpoints), "", User('STAFF_ID'), "%") . '%&nbsp;' . _makeLetterGrade($total_stpoints / $total_asgnpoints, "", User('STAFF_ID')) . '</font>', 'WEIGHT_GRADE' => '<font style="font-size:13;font-weight:bold;">' . ($programconfig[User('STAFF_ID')]['WEIGHT'] == 'Y' ? _makeLetterGrade($tot_weight_grade, "", User('STAFF_ID'), '%') . '%&nbsp;' . _makeLetterGrade($tot_weight_grade, "", User('STAFF_ID')) : 'N/A') . '</font>');
                } else {
                    $link['add']['html'] = array('TITLE' => '<font style="font-size:13;font-weight:bold;"><B>Total</B></font>', 'LETTER_GRADE' => '<font style="font-size:13;font-weight:bold;">Not Graded</font>');
                }
//                        $link['add']['html']['ASSIGNED_DATE'] = $link['add']['html']['DUE_DATE'] = $link['add']['html']['POINTS'] = $link['add']['html']['COMMENT'] = ' &nbsp; ';
                $link['add']['html']['ASSIGNED_DATE'] = $link['add']['html']['DUE_DATE'] = $link['add']['html']['COMMENT'] = ' &nbsp; ';
                echo '</table>';
                ListOutputPrint($grades_RET, $columns, 'Assignment', 'Assignments', $link, array(), array('center' => false, 'add' => true));
                echo '<div style="page-break-before: always;">&nbsp;</div>';
            }

            PDFStop($handle);
        } else
            BackPrompt('No Students were found.');
    } else
        BackPrompt('You must choose at least one student.');
}

if (!$_REQUEST['modfunc']) {
    if ($_REQUEST['pr'] == 1) {
        $extra['skip_search'] = 'Y';
        $_REQUEST['search_modfunc'] = 'list';
    }
    if ($_REQUEST['search_modfunc'] == 'list') {
        echo "<FORM action=ForExport.php?modname=" . strip_tags(trim($_REQUEST[modname])) . "&modfunc=save&include_inactive=" . strip_tags(trim($_REQUEST[include_inactive])) . "&_openSIS_PDF=true&head_html=Student+Progress+Report method=POST target=_blank>";
        Widgets('mailing_labels');
        $extra['extra_header_left'] = '<div class="form-group"><div class="checkbox">';
        $extra['extra_header_left'] .= '<label class="checkbox-inline"><INPUT type=checkbox value=Y name=assigned_date>Assigned Date</label>';
        $extra['extra_header_left'] .= '<label class="checkbox-inline"><INPUT type=checkbox value=Y name=exclude_ec checked>Exclude Ungraded E/C Assignments</label>';
        $extra['extra_header_left'] .= '<label class="checkbox-inline"><INPUT type=checkbox value=Y name=due_date checked>Due Date</label>';
        $extra['extra_header_left'] .= '<label class="checkbox-inline"><INPUT type=checkbox value=Y name=exclude_notdue>Exclude Ungraded Assignments Not Due</label>';
        $extra['extra_header_left'] .= '</div></div>';
        $extra['extra_header_left'] .= $extra['search'];
        $extra['search'] = '';
    }

    $extra['link'] = array('FULL_NAME' => false);
    $extra['SELECT'] = ",s.STUDENT_ID AS CHECKBOX";
    $extra['functions'] = array('CHECKBOX' => '_makeChooseCheckbox');
    $extra['columns_before'] = array('CHECKBOX' => '</A><INPUT type=checkbox value=Y name=controller checked onclick="checkAll(this.form,this.form.controller.checked,\'st_arr\');"><A>');
    $extra['options']['search'] = false;
    $extra['new'] = true;


    Search('student_id', $extra, 'true');
    if ($_REQUEST['search_modfunc'] == 'list') {
        if ($_SESSION['count_stu'] != 0)
            echo '<BR><CENTER><INPUT type=submit value=\'Create Progress Reports for Selected Students\'  class="btn btn-primary"></CENTER>';
        echo "</FORM>";
    }
}

function _makeExtra($value, $column) {
    global $THIS_RET, $student_points, $total_points, $percent_weights;

    if ($column == 'POINTS') {
        if ($THIS_RET['TOTAL_POINTS'] != '0')
            if ($value != '-1') {
                if (($THIS_RET['DUE'] || $value != '') && $value != '') {
                    $student_points[$THIS_RET['ASSIGNMENT_TYPE_ID']] += $value;
                    $total_points[$THIS_RET['ASSIGNMENT_TYPE_ID']] += $THIS_RET['TOTAL_POINTS'];
                    $percent_weights[$THIS_RET['ASSIGNMENT_TYPE_ID']] = $THIS_RET['FINAL_GRADE_PERCENT'];
                }
                return '<TABLE border=0 cellspacing=0 cellpadding=0 class=LO_field><TR><TD><font size=-1>' . (rtrim(rtrim($value, '0'), '.') + 0) . '</font></TD><TD><font size=-1>&nbsp;/&nbsp;</font></TD><TD><font size=-1>' . $THIS_RET['TOTAL_POINTS'] . '</font></TD></TR></TABLE>';
            } else
                return '<TABLE border=0 cellspacing=0 cellpadding=0 class=LO_field><TR><TD><font size=-1>Excluded</font></TD><TD></TD><TD></TD></TR></TABLE>';
        else {
            $student_points[$THIS_RET['ASSIGNMENT_TYPE_ID']] += $value;
            return '<TABLE border=0 cellspacing=0 cellpadding=0 class=LO_field><TR><TD><font size=-1>' . (rtrim(rtrim($value, '0'), '.') + 0) . '</font></TD><TD><font size=-1>&nbsp;/&nbsp;</font></TD><TD><font size=-1>' . $THIS_RET['TOTAL_POINTS'] . '</font></TD></TR></TABLE>';
        }
    } elseif ($column == 'LETTER_GRADE') {
        if ($THIS_RET['TOTAL_POINTS'] != '0')
            if ($value != '-1')
                if ($THIS_RET['DUE'] && $value == '')
                    return 'Not Graded';
                else if ($THIS_RET['DUE'] || $value != '') {

                    $per = $value / $THIS_RET['TOTAL_POINTS'];

                    return _makeLetterGrade($per, "", User('STAFF_ID'), "%") . '%&nbsp;' . _makeLetterGrade($value / $THIS_RET['TOTAL_POINTS'], "", User('STAFF_ID'));
                } else
                    return 'Due';
            else
                return 'N/A';
        else
            return 'E/C';
    }
}

function _removeSpaces($value, $column) {
    if ($column == 'ASSIGNED_DATE' || $column == 'DUE_DATE')
        $value = ProperDate($value);
    if ($column == 'TITLE')
        $value = html_entity_decode($value);
    return str_replace(' ', '&nbsp;', str_replace('&', '&amp;', $value));
}

function _makeChooseCheckbox($value, $title) {
    return '<INPUT type=checkbox name=st_arr[] value=' . $value . ' checked>';
}

function _makeAssnWG($value, $column) {
    global $THIS_RET, $student_points, $total_points, $percent_weights;
    return ($THIS_RET['ASSIGN_TYP_WG'] != 'N/A' ? ($value * 100) . ' %' : $THIS_RET['ASSIGN_TYP_WG']);
}

function _makeWtg($value, $column) {
    global $THIS_RET, $student_points, $total_points, $percent_weights;
    $wtdper = ($THIS_RET['POINTS'] / $THIS_RET['TOTAL_POINTS']) * $THIS_RET['FINAL_GRADE_PERCENT'];
    return (($THIS_RET['LETTERWTD_GRADE'] != -1.00 && $THIS_RET['LETTERWTD_GRADE'] != '' && $THIS_RET['ASSIGN_TYP_WG'] != 'N/A') ? _makeLetterGrade($wtdper, "", User('STAFF_ID'), '%') . '%' : 'N/A');
}

?>