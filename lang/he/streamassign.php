<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Hebrew strings for mod_streamassign.
 *
 * @package    mod_streamassign
 * @copyright  2025 mattandor <mattan@centricapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['modulename'] = 'הגשת מטלה Stream';
$string['modulenameplural'] = 'הגשת מטלות Stream';
$string['modulename_help'] = 'הסטודנט מגיש קובץ וידאו שמועלה למערכת Stream החיצונית.';
$string['pluginname'] = 'הגשת מטלה Stream';
$string['pluginadministration'] = 'ניהול הגשת מטלה Stream';
$string['streamassign:view'] = 'צפייה במטלת Stream';
$string['streamassign:submit'] = 'הגשת וידאו למטלת Stream';
$string['streamassign:addinstance'] = 'הוספת מטלת Stream';
$string['streamassign:grade'] = 'ציון מטלת Stream';

$string['streamassignsettings'] = 'הגדרות הגשת מטלה Stream';
$string['streamurl_required'] = 'כתובת Stream ומפתח API חייבים להיות מוגדרים בתוסף local_stream (ניהול אתר → תוספים → תוספים מקומיים → Stream).';
$string['submitvideo'] = 'הגשת וידאו';
$string['submission'] = 'הגשה';
$string['nosubmission'] = 'טרם הוגש';
$string['yoursubmission'] = 'ההגשה שלך';
$string['submittedon'] = 'הוגש בתאריך';
$string['watchvideo'] = 'צפייה בוידאו';
$string['uploadvideo'] = 'העלאת וידאו';
$string['videotitle'] = 'כותרת הוידאו (אופציונלי)';
$string['videotitle_help'] = 'כותרת הוידאו במערכת Stream. אם ריק, ישתמש בשם הקובץ.';
$string['allowedformats'] = 'פורמטים נתמכים: MP4, WebM, MKV, AVI, MOV ופורמטי וידאו נפוצים אחרים.';
$string['uploadsuccess'] = 'הוידאו הועלה בהצלחה ל-Stream.';
$string['uploaderror'] = 'ההעלאה נכשלה';
$string['activitynotavailableyet'] = 'פעילות זו אינה זמינה עד {$a}.';
$string['activityclosed'] = 'פעילות זו נסגרה ב-{$a}.';
$string['nostreamassignments'] = 'אין מטלות Stream';
$string['privacy:metadata:streamassign_submission'] = 'מאחסן את מזהה הוידאו ב-Stream וזמן ההגשה לכל משתמש.';
$string['privacy:metadata:streamplatform'] = 'קבצי וידאו מועלים למערכת Stream חיצונית; אימייל ושם משתמש עשויים להישלח לצורכי בעלות.';

// Settings page - connection status
$string['connectionstatus'] = 'סטטוס חיבור ל-Stream';
$string['connection_streamurl'] = 'כתובת Stream (local_stream):';
$string['connection_apikey'] = 'מפתח API (local_stream):';
$string['connection_configured'] = 'מוגדר';
$string['connection_notset'] = 'לא הוגדר';
$string['connection_reach'] = 'נגישות שרת:';
$string['connection_ok'] = 'תקין';
$string['connection_failed'] = 'לא ניתן להגיע לשרת (בדוק כתובת או רשת)';
$string['connection_ready'] = 'Stream מוגדר ונגיש. התוסף מוכן לשימוש.';
$string['connection_configured_not_reachable'] = 'כתובת Stream ומפתח API מוגדרים, אך לא ניתן היה להגיע לשרת Stream.';
$string['connection_not_configured'] = 'Stream לא מוגדר. הגדר כתובת Stream ומפתח API בתוסף local_stream (ניהול אתר → תוספים → תוספים מקומיים → Stream).';
$string['connectioninfo'] = 'אודות הגדרות Stream';
$string['connectioninfo_desc'] = 'פעילות זו משתמשת בכתובת Stream ובמפתח API מתוסף local_stream. לשינוי, עבור לניהול אתר → תוספים → תוספים מקומיים → Stream.';

// Grading
$string['grading'] = 'ציון הגשות';
$string['viewgrading'] = 'צפייה והערכת הגשות';
$string['savegrades'] = 'שמירת ציונים';
$string['gradesupdated'] = 'הציונים נשמרו.';
$string['nosubmissionsgrading'] = 'אין הגשות לציון.';
$string['backtoactivity'] = 'חזרה לפעילות';
$string['feedback'] = 'הערות';
$string['clearfilter'] = 'הצג הכל';

// Submission summary (view page, for graders)
$string['submissionsummary'] = 'סיכום הגשות';
$string['numberofparticipants'] = 'משתתפים';
$string['numberofsubmitted'] = 'הוגשו';
$string['numberofneedgrading'] = 'ממתינים לציון';
$string['numberofnotsubmitted'] = 'טרם הגישו';

$string['thumbnail'] = 'תמונה ממוזערת';
$string['nothumbnail'] = 'אין תמונה';

// Submit: choose existing video or upload new
$string['choosemethod'] = 'איך ברצונך להגיש?';
$string['selectexisting'] = 'בחירה מסרטונים קיימים שלי';
$string['uploadnew'] = 'העלאת סרטון חדש';
$string['myvideos'] = 'הסרטונים שלי';
$string['myvideos_help'] = 'סרטונים שכבר העלית למערכת Stream. בחר אחד להגשה במטלה זו.';
$string['selectvideo'] = 'בחר סרטון...';
$string['pleaseselectvideo'] = 'נא לבחור סרטון.';
$string['noexistingvideos'] = 'אין לך עדיין סרטונים ב-Stream. העלה סרטון חדש למטה.';

$string['videoprocessing'] = 'הסרטון עדיין בעיבוד. הנגן יופיע כאן כשיהיה מוכן (נבדוק כל 30 שניות).';
