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
 * @copyright  2025 CentricApp LTD, Dev Team (dev@centricapp.co)
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
$string['messageprovider:submission'] = 'התראות על הגשות במטלת Stream';
$string['messageprovider:gradeupdated'] = 'התראות על עדכון ציון במטלת Stream';

$string['streamassignsettings'] = 'הגדרות הגשת מטלה Stream';
$string['submissionsettings'] = 'הגדרות הגשה';
$string['notificationsettings'] = 'התראות';
$string['preventlatesubmission'] = 'מניעת הגשה מאוחרת';
$string['allowresubmission'] = 'אפשר הגשה מחדש';
$string['emailalertstoteachers'] = 'התראות מייל למרצים';
$string['notifygraderssubmission'] = 'התראה למעריכים על הגשה';
$string['notifygraderslatesubmission'] = 'התראה למעריכים על הגשה מאוחרת';
$string['notifystudentdefault'] = "ברירת מחדל עבור 'עדכן סטודנט'";
$string['notifystudent'] = 'עדכן סטודנט';
$string['streamurl_required'] = 'כתובת Stream ומפתח API חייבים להיות מוגדרים בתוסף local_stream (ניהול אתר → תוספים → תוספים מקומיים → Stream).';
$string['submitvideo'] = 'הגשת וידאו';
$string['submission'] = 'הגשה';
$string['nosubmission'] = 'טרם הוגש';
$string['yoursubmission'] = 'ההגשה שלך';
$string['submittedon'] = 'הוגש בתאריך';
$string['watchvideo'] = 'צפייה בוידאו';
$string['uploadvideo'] = 'העלאת וידאו';
$string['uploadzonetext'] = 'ניתן לגרור ולהשליך קבצים לכאן.';
$string['uploadzonehint'] = 'גודל קובץ מקסימלי: 2GB. פורמטים: MP4, WebM, MOV, AVI ועוד.';
$string['uploadinprogress'] = 'מעלה…';
$string['uploadtoolarge'] = 'הקובץ חורג מהגודל המקסימלי (2GB).';
$string['videotitle'] = 'כותרת הוידאו (אופציונלי)';
$string['videotitle_help'] = 'כותרת הוידאו במערכת Stream. אם ריק, ישתמש בשם הקובץ.';
$string['allowedformats'] = 'פורמטים נתמכים: MP4, WebM, MKV, AVI, MOV ופורמטי וידאו נפוצים אחרים.';
$string['uploadsuccess'] = 'הוידאו הועלה בהצלחה ל-Stream.';
$string['readytoupload'] = 'לחץ על "הגשת וידאו" כדי להעלות קובץ זה.';
$string['uploaderror'] = 'ההעלאה נכשלה';
$string['resubmissionnotallowed'] = 'לא ניתן להגיש מחדש בפעילות זו.';
$string['activitynotavailableyet'] = 'פעילות זו אינה זמינה עד {$a}.';
$string['activityclosed'] = 'פעילות זו נסגרה ב-{$a}.';
$string['nostreamassignments'] = 'אין מטלות Stream';
$string['privacy:metadata:streamassign_submission'] = 'מאחסן את מזהה הוידאו ב-Stream וזמן ההגשה לכל משתמש.';
$string['privacy:metadata:streamassign_submission:userid'] = 'המשתמש שביצע את ההגשה.';
$string['privacy:metadata:streamassign_submission:streamid'] = 'מזהה הוידאו במערכת Stream החיצונית.';
$string['privacy:metadata:streamassign_submission:videotitle'] = 'כותרת הוידאו שהוגש.';
$string['privacy:metadata:streamassign_submission:timecreated'] = 'מתי נוצרה ההגשה.';
$string['privacy:metadata:streamassign_submission:timemodified'] = 'מתי עודכנה ההגשה לאחרונה.';
$string['privacy:metadata:core_grades'] = 'ציונים והערות נשמרים במערכת הציונים של Moodle.';
$string['privacy:metadata:streamplatform'] = 'קבצי וידאו מועלים למערכת Stream חיצונית; אימייל ושם משתמש עשויים להישלח לצורכי בעלות.';
$string['privacy:metadata:streamplatform:userid'] = 'מזהה משתמש עשוי להישלח למערכת Stream לצורכי בעלות.';
$string['privacy:metadata:streamplatform:videotitle'] = 'כותרת הוידאו נשלחת למערכת Stream.';
$string['privacy:metadata:streamplatform_reachability'] = 'התוסף יוצר קשר עם כתובת Stream המוגדרת מדף הגדרות הפעילות כדי לבדוק נגישות השרת. לא נשלחים נתונים אישיים בבקשה זו.';

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
$string['grade'] = 'ציון';

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
$string['searchmyvideos'] = 'חיפוש סרטונים...';
$string['selected'] = 'נבחר';

$string['videoprocessing'] = 'הסרטון עדיין בעיבוד. הנגן יופיע כאן כשיהיה מוכן (נבדוק כל 30 שניות).';
$string['notificationnewsubmission'] = '{$a->student} הגיש/ה וידאו ב-{$a->activity}.';
$string['notificationnewsubmissionbody'] = 'התקבלה הגשת וידאו חדשה.' . "\n"
    . 'סטודנט/ית: {$a->student}' . "\n"
    . 'קורס: {$a->course}' . "\n"
    . 'פעילות: {$a->activity}' . "\n"
    . 'כותרת וידאו: {$a->videotitle}';
$string['notificationlatesubmission'] = '{$a->student} הגיש/ה וידאו באיחור ב-{$a->activity}.';
$string['notificationlatesubmissionbody'] = 'התקבלה הגשת וידאו מאוחרת.' . "\n"
    . 'סטודנט/ית: {$a->student}' . "\n"
    . 'קורס: {$a->course}' . "\n"
    . 'פעילות: {$a->activity}' . "\n"
    . 'כותרת וידאו: {$a->videotitle}';
$string['notificationgradeupdated'] = 'הציון/המשוב שלך עודכנו ב-{$a->activity}.';
$string['notificationgradeupdatedbody'] = 'הציון או המשוב שלך עודכנו.' . "\n"
    . 'קורס: {$a->course}' . "\n"
    . 'פעילות: {$a->activity}';
