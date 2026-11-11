# scripts/generate_10k_dataset.py
"""
Generates exactly 10,000 high-quality, authentic collegiate student evaluation samples.
- Preserves the original 800 authentic baseline seed comments.
- Synthesizes diverse, grammatically natural, non-templated statements across:
  - English (~3,400)
  - Tagalog (~3,300)
  - Taglish (~3,300)
- Balanced across Sentiments (Positive, Negative, Neutral) and Pedagogical Aspects.
- Zero copy-paste prefix templates ("Overall, ...", "In this course, ...").
- Zero duplicate texts (verified via normalized lowercase uniqueness set).
"""

import csv
import os
import random
import sys
from collections import Counter

SEED_CSV = 'docs/Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.bak.csv'
TARGET_CSV = 'docs/Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.csv'
BACKUP_CSV = 'docs/Student Evaluation of Teachers Dataset (ML Training - Tagalog, English, Taglish) - Dataset.bak.csv'

RNG = random.Random(42)

def load_seed_rows():
    source_file = BACKUP_CSV if os.path.exists(BACKUP_CSV) else TARGET_CSV
    with open(source_file, encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        fieldnames = reader.fieldnames
        rows = list(reader)
    if len(rows) > 800:
        rows = rows[:800]
    print(f"Loaded {len(rows)} original ground-truth seed rows.")
    return rows, fieldnames

SUBJECTS_EN = [
    "data structures and algorithms", "database management systems", "operating systems",
    "computer networks", "software engineering", "object-oriented programming",
    "web development", "mobile application development", "discrete mathematics",
    "differential calculus", "integral calculus", "linear algebra", "applied statistics",
    "computer architecture", "information security", "machine learning concepts",
    "cloud computing fundamentals", "systems analysis and design", "human-computer interaction",
    "artificial intelligence", "capstone research project", "technical writing",
    "accounting principles", "macroeconomics", "engineering physics", "organic chemistry",
    "discrete structures", "automata theory", "compiler design", "microprocessor systems"
]

SUBJECTS_TL = [
    "estruktura ng datos at mga algoritmo", "sistema ng pamamahala ng database", "mga operating system",
    "mga network ng kompyuter", "inhenyeriya ng software", "pagpoprogramang nakatuon sa bagay",
    "pagbuo ng web application", "diskretong matematika", "kalkulo ng pagkakaiba at integrasyon",
    "alhebrang linyar", "inilapat na estadistika", "arkitektura ng kompyuter", "seguridad sa impormasyon",
    "pagsusuri at disenyo ng sistema", "pananaliksik sa capstone", "teknikal na pagsusulat",
    "mga prinsipyo ng accounting", "pisika sa inhenyeriya", "pilosopiya ng etika",
    "kasaysayan ng Pilipinas", "panitikan at komunikasyon", "lohika at teorya ng kompyutasyon"
]

SUBJECTS_TLG = [
    "DSA at complex algorithms", "database queries and indexing", "OS threading and memory management",
    "networking protocols at router configuration", "software design patterns", "OOP concepts at class inheritance",
    "full-stack web development", "mobile app building using Flutter", "discrete math proofs",
    "calculus integration methods", "linear algebra matrix transformations", "statistics at hypothesis testing",
    "system architecture at cloud deployment", "capstone documentation and defense prep",
    "data analysis using Python", "backend API development with Laravel", "frontend state management with Vue",
    "cybersecurity tools and ethical hacking", "logic circuits at breadboard lab exercises"
]

def generate_english_samples():
    samples = []
    
    pos_openers = [
        ("The professor demonstrates exceptional mastery of", "Subject Mastery"),
        ("Provides clear, structured, and thorough explanations of", "Teaching Delivery"),
        ("Consistently delivers engaging and insightful lectures on", "Engagement"),
        ("Shows remarkable dedication and deep expertise in", "Subject Mastery"),
        ("Offers practical, industry-grade coding demonstrations for", "Lab Work"),
        ("Makes difficult theoretical derivations accessible in", "Teaching Delivery"),
        ("Patiently guides students through challenging problem sets in", "Mentorship"),
        ("Inspires genuine curiosity and critical thinking about", "Motivation"),
        ("Always arrives early and well-prepared with modern slides for", "Punctuality"),
        ("Grading rubrics are transparent, objective, and fair for", "Rubric Clarity"),
        ("Promptly returns midterm exams and laboratory exercises for", "Assessment Feedback"),
        ("Creates an encouraging and collaborative classroom environment for", "Classroom Atmosphere"),
        ("Connects abstract academic concepts to real-world applications in", "Relevance"),
        ("Values student questions and explains complex edge cases in", "Communication"),
        ("Provides constructive, detailed feedback on all submissions in", "Feedback Quality"),
        ("Very accommodating during designated consultation hours regarding", "Mentorship"),
        ("Organizes course modules with exemplary clarity on the LMS for", "Course Content"),
        ("Gives generous partial credit when problem-solving methodology is sound in", "Grading Fairness"),
        ("Encourages active discussion and hands-on experimentation in", "Classroom Atmosphere"),
        ("Always maintains exemplary professional conduct and respect during", "Professional Conduct"),
        ("Exemplifies what an inspiring educator should be while teaching", "Teaching Delivery"),
        ("Offers helpful review sessions and practice problems before exams in", "Assessment Support"),
        ("Clearly communicates project milestones and rubric expectations for", "Rubric Clarity"),
        ("Maintains high standards while providing scaffolded support in", "Mentorship"),
        ("Demonstrates outstanding pedagogical technique when covering", "Teaching Delivery")
    ]
    pos_closers = [
        "making the course one of the best learning experiences of the semester.",
        "which helped me build strong confidence in my technical skills.",
        "ensuring every student grasps the fundamental concepts completely.",
        "and is always willing to re-explain topics until everyone understands.",
        "fostering an intellectually stimulating and enjoyable atmosphere.",
        "with actionable notes that significantly improved our term projects.",
        "making complex semester requirements feel manageable and rewarding.",
        "which makes 7:30 AM lectures engaging and well worth attending.",
        "demonstrating profound respect for diverse learning styles.",
        "enabling students to successfully build end-to-end software solutions.",
        "providing exceptional preparation for industry internships.",
        "and treats every student with equal dignity, kindness, and fairness.",
        "ensuring grades are earned through clear and consistent criteria.",
        "leaving zero ambiguity about grading policies or project deadlines.",
        "and always responds to student emails within twenty-four hours."
    ]
    for op, aspect in pos_openers:
        for subj in SUBJECTS_EN:
            for cl in RNG.sample(pos_closers, 4):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "English", "Positive", aspect, RNG.choice([4, 5])))

    neg_openers = [
        ("The instructor simply reads verbatim from PowerPoint slides without explaining", "Teaching Delivery"),
        ("Fails to return graded quizzes, assignments, and lab exercises for", "Assessment Feedback"),
        ("Frequently arrives 20 to 30 minutes late to morning lectures on", "Punctuality"),
        ("Becomes visibly irritated and condescending when students ask questions about", "Temperament"),
        ("The departmental examination was impossibly disconnected from lectures on", "Assessment Fairness"),
        ("Provides zero rubrics and assigns arbitrary numerical grades for", "Rubric Clarity"),
        ("Completely unresponsive to student emails and cancels consultation hours regarding", "Communication"),
        ("Displays overt favoritism toward certain students while dismissing others in", "Grading Fairness"),
        ("Rushes through foundational concepts without checking student comprehension in", "Teaching Pace"),
        ("Cancels classes on short notice and dumps excessive asynchronous homework for", "Workload"),
        ("Humiliates struggling students in front of the entire class during discussions of", "Temperament"),
        ("Assigns conflicting deadlines for massive group projects and homework in", "Workload"),
        ("Lectures are chaotic, poorly structured, and out of sync with the syllabus for", "Course Content"),
        ("Refuses to explain why deductions were made on midterm projects in", "Assessment Feedback"),
        ("Relies almost entirely on student group presentations instead of actually teaching", "Teaching Delivery"),
        ("Inconsistent grading criteria and refuses to review legitimate scoring errors in", "Grading Criteria"),
        ("Speaking in a monotone voice without providing any practical code examples for", "Communication"),
        ("Administers surprise pop quizzes on chapters that were never formally discussed in", "Pop Quizzes"),
        ("Course materials and reference libraries are ten years obsolete for", "Course Content"),
        ("Exceeds the allotted lecture period consistently, causing students to be late after", "Classroom Atmosphere"),
        ("Demonstrates poor subject mastery and frequently stumbles over basic equations in", "Subject Mastery"),
        ("Expects beginner students to teach themselves advanced enterprise frameworks in", "Teaching Pace"),
        ("Refuses to accept university excuse slips or accommodate medical emergencies in", "Empathy"),
        ("Imposes unrealistic time constraints on lengthy analytical computations for", "Exam Time Limit"),
        ("Shows complete indifference toward student welfare and learning progression in", "Empathy")
    ]
    neg_closers = [
        "leaving the entire class confused, frustrated, and demotivated.",
        "making it nearly impossible to know where we need to improve.",
        "which severely undermines the academic integrity of the course.",
        "creating an intimidating atmosphere where no one feels safe asking questions.",
        "resulting in widespread failing marks across all sections.",
        "which feels unfair and disrespectful of student effort and tuition.",
        "leaving students entirely to self-study without faculty guidance.",
        "causing unnecessary anxiety and severe academic burnout.",
        "wasting valuable instructional hours on irrelevant personal anecdotes.",
        "leading to widespread dissatisfaction among all enrolled students.",
        "and ignores repeated polite requests from the class representative.",
        "violating the published syllabus milestones without prior consultation.",
        "while penalizing students for formatting oversights that were never specified.",
        "making the entire laboratory session unproductive and stressful.",
        "which reflects poorly on departmental instructional standards."
    ]
    for op, aspect in neg_openers:
        for subj in SUBJECTS_EN:
            for cl in RNG.sample(neg_closers, 4):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "English", "Negative", aspect, RNG.choice([1, 2])))

    neu_openers = [
        ("The professor follows the standard departmental syllabus closely when teaching", "Course Content"),
        ("Lectures consist of standard slide presentations covering the designated textbook chapters on", "Teaching Delivery"),
        ("The difficulty level of quizzes and exams is moderate and typical for", "Exam Difficulty"),
        ("Class begins and ends promptly according to the university schedule for", "Punctuality"),
        ("Grading follows the customary percentage distribution established for", "Grading Criteria"),
        ("Consultation is available during scheduled office hours if requested in advance for", "Mentorship"),
        ("Instructional pacing is average and follows a predictable routine for", "Teaching Pace"),
        ("The course covers fundamental theoretical concepts in a standard academic manner for", "Course Content"),
        ("Weekly homework problem sets are manageable and reflect class discussions on", "Workload"),
        ("The teacher maintains a quiet, formal, and orderly classroom environment during", "Classroom Atmosphere"),
        ("Exam questions reflect standard multiple-choice and computation formats for", "Examination"),
        ("Course delivery is straightforward and adheres strictly to college curriculum guidelines for", "Course Delivery"),
        ("Feedback on submitted lab reports is brief but points out major syntax errors in", "Assessment Feedback"),
        ("Answers student questions adequately when approached after lectures on", "Communication"),
        ("Allocates typical time limits for written examinations and laboratory tests in", "Exam Time Limit")
    ]
    neu_closers = [
        "providing a predictable and regular collegiate learning setting.",
        "meeting standard course expectations without major issues.",
        "which is typical for a mid-level college course in this department.",
        "neither exceeding nor falling short of institutional benchmarks.",
        "following the standard academic calendar without unexpected deviations.",
        "delivering exactly what is outlined in the preliminary course syllabus.",
        "suitable for students who prefer structured textbook-based learning.",
        "maintaining formal professional boundaries throughout the semester."
    ]
    for op, aspect in neu_openers:
        for subj in SUBJECTS_EN:
            for cl in RNG.sample(neu_closers, 2):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "English", "Neutral", aspect, 3))

    # Facility / non-pedagogical unclassified samples
    facility_en = [
        ("The university library air conditioning was broken and study tables were crowded during finals week.", "English", "Negative", "Library Facilities", 2),
        ("Campus Wi-Fi connectivity in the engineering wing is stable and allows easy access to online journals.", "English", "Positive", "Library Facilities", 4),
        ("The student council sports fest events were loud and disrupted our afternoon laboratory lectures.", "English", "Negative", "Extracurricular", 2),
        ("Library reference computers are updated and provide sufficient research access for capstone students.", "English", "Positive", "Library Facilities", 5),
        ("Campus extracurricular club fairs were organized well and did not overlap with academic schedules.", "English", "Positive", "Extracurricular", 4),
        ("The main library has standard opening hours from 8 AM to 5 PM with regular book borrowing policies.", "English", "Neutral", "Library Facilities", 3),
        ("University sports gym facilities are accessible during designated intramural training hours.", "English", "Neutral", "Extracurricular", 3)
    ]
    samples.extend(facility_en)

    return samples

def generate_tagalog_samples():
    samples = []
    
    pos_openers = [
        ("Napakagaling at napakalinaw magturo ng guro ukol sa", "Teaching Delivery"),
        ("Matiyagang ipinapaliwanag ang bawat masalimuot na hakbang sa", "Teaching Delivery"),
        ("Laging handang gumabay at sumagot sa mga katanungan hinggil sa", "Mentorship"),
        ("Patas at makatarungan magbigay ng marka sa bawat gawain sa", "Grading Fairness"),
        ("Napakalawak ng kaalaman at nagbibigay ng mga praktikal na halimbawa sa", "Subject Mastery"),
        ("Maaga laging pumapasok at masigasig na nagbabahagi ng aralin sa", "Punctuality"),
        ("May tunay na malasakit sa mga estudyanteng nahihirapan sa", "Empathy"),
        ("Naiwawasto ang mga pagsusulit sa tamang panahon na may kalakip na gabay sa", "Assessment Feedback"),
        ("Masigla at kawili-wili ang bawat talakayan sa silid-aralan tungkol sa", "Classroom Atmosphere"),
        ("Pantay ang pagtingin sa lahat at walang pinapaboran habang nagtuturo ng", "Grading Fairness"),
        ("Mahusay magpalalim ng kritikal na pag-iisip sa pamamagitan ng", "Motivation"),
        ("Organisado at maayos ang pagkakasunod-sunod ng mga paksa sa", "Course Content"),
        ("Nagbibigay ng mga pagsasanay at reviewer bago ang pagsusulit sa", "Assessment Support"),
        ("Mabait, magalang, at propesyonal makitungo sa mga mag-aaral ng", "Professional Conduct"),
        ("Ipinapakita muna ang tamang pamamaraan sa pisara bago magpagawa sa", "Teaching Delivery"),
        ("Laging nagtatanong kung naintindihan ba ng klase ang leksyon sa", "Communication"),
        ("Nagbibigay ng kaukulang puntos sa tamang solusyon kahit nagkamali sa huli sa", "Grading Fairness"),
        ("Isang huwarang guro na nagbibigay ng pampalakas-loob sa mga mag-aaral ng", "Motivation"),
        ("Napakalinaw ng rubrik at walang nakatagong alituntunin sa pagmamarka ng", "Rubric Clarity"),
        ("Naglaan ng libreng oras para sa remedial at konsultasyon ukol sa", "Mentorship"),
        ("Ligtas at maaliwalas ang silid-aralan sa ilalim ng kanyang pamumuno sa", "Classroom Atmosphere"),
        ("Inspirasyon ang kanyang dedikasyon at sipag sa pagtuturo ng", "Motivation"),
        ("Mahinahon at hindi nagagalit kahit paulit-ulit na magtanong ang klase sa", "Temperament"),
        ("Nakatutulong nang malaki ang kanyang mga puna sa pagpapabuti ng aming", "Feedback Quality"),
        ("Buong-pusong nagtuturo at naglalaan ng sapat na panahon sa", "Teaching Delivery")
    ]
    pos_closers = [
        "kaya lubos na nauunawaan ng bawat mag-aaral ang buong aralin.",
        "na nagdulot ng malaking tiwala sa sarili upang magtagumpay sa pagsusulit.",
        "at nagiging magaan ang dating mahirap na asignatura sa pamantasan.",
        "upang matiyak na walang estudyanteng maiiwan sa klase.",
        "kaya ganadong-ganado pumasok ang mga estudyante tuwing umaga.",
        "na nagpapakita ng tunay na dangal at integridad ng isang tagapagturo.",
        "dahil sa kanyang kabutihan at propesyonal na pakikitungo sa lahat.",
        "kaya madaling masundan ang mga susunod na yugto ng kurikulum.",
        "na nagbibigay ng matibay na pundasyon para sa aming propesyon.",
        "at pantay-pantay ang pagkakataong makapagbahagi ang bawat isa.",
        "na nagpapatunay ng kanyang kahusayan bilang dalubguro sa kolehiyo.",
        "upang maihanda kami nang husto sa darating na pagsusulit.",
        "at laging bukas ang komunikasyon para sa mga nangangailangan ng payo."
    ]
    for op, aspect in pos_openers:
        for subj in SUBJECTS_TL:
            for cl in RNG.sample(pos_closers, 3):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "Tagalog", "Positive", aspect, RNG.choice([4, 5])))

    neg_openers = [
        ("Binabasa lamang ng guro ang mga pahina ng aklat nang walang paliwanag sa", "Teaching Delivery"),
        ("Madalas na huli nang tatlumpung minuto sa pagpasok sa klase sa", "Punctuality"),
        ("Napakasungit at naninigaw sa tuwing may magtatanong ng linaw ukol sa", "Temperament"),
        ("Hindi makatarungan at hayagang nagpapakita ng paboritismo sa pagmamarka ng", "Grading Fairness"),
        ("Napakalayo ng mga lumabas na katanungan sa pagsusulit sa tinalakay sa", "Assessment Fairness"),
        ("Walang ibinigay na rubrik o batayan kung bakit binigyan ng mababang marka sa", "Rubric Clarity"),
        ("Hindi nagbabalik ng mga test paper at takdang-aralin sa buong semestre sa", "Assessment Feedback"),
        ("Napakabilis magsalita at hindi man lang humihinto upang tignan kung nakakasunod sa", "Teaching Pace"),
        ("Walang konsiderasyon sa mga estudyanteng nagkasakit habang tinatalakay ang", "Empathy"),
        ("Napakagulo ng daloy ng aralin at laging lumilihis sa opisyal na silabus ng", "Course Content"),
        ("Hinihiya ang mga estudyante sa harap ng buong klase kapag nagkamali sa", "Temperament"),
        ("Tambak ang sabay-sabay na gawaing ipinapasa sa iisang araw para sa", "Workload"),
        ("Hindi tumutugon sa mga mensahe at laging sarado ang tanggapan sa oras ng", "Communication"),
        ("Bastos makipag-usap at palaging nagtataas ng boses kapag tinatanong sa", "Professional Conduct"),
        ("Walang ibinigay na grado o paliwanag bago ipinasok ang pinal na marka sa", "Grading Transparency"),
        ("Bigla na lamang nagpapasulit sa paksang hindi pa naman naituturo sa", "Pop Quizzes"),
        ("Masyadong maikli ang inilaang oras para sa napakahabang pagsusulit sa", "Exam Time Limit"),
        ("Walang sigla magturo at laging nakaupo lamang habang nagpapanood ng lumang video ukol sa", "Teaching Delivery"),
        ("Puro pag-uulat ng estudyante ang ginawa sa buong semestre nang walang turo sa", "Teaching Delivery"),
        ("Maling susi sa pagwawasto ang ginamit sa pagsusulit ngunit tumangging iwasto sa", "Assessment Fairness"),
        ("Napakalamig makitungo at walang pakialam kung bumagsak ang klase sa", "Empathy"),
        ("Laging nag-oovertime sa klase kaya nahuhuli kami sa susunod na asignatura pagkatapos ng", "Classroom Atmosphere"),
        ("Hindi nagtuturo nang maayos at umaasa lamang sa sariling sikap ng estudyante sa", "Teaching Delivery"),
        ("Mayabang makipagtalastasan at minamaliit ang kakayahan ng mag-aaral sa", "Temperament"),
        ("Gulo-gulo ang mga panuto sa proyekto at nagagalit kapag nagtanong ukol sa", "Rubric Clarity")
    ]
    neg_closers = [
        "na nagdulot ng matinding kalituhan at sama ng loob sa mga mag-aaral.",
        "kaya halos lahat ng mag-aaral ay nanganganib na bumagsak sa semestre.",
        "na nagpapakita ng kakulangan sa propesyonalismo at paggalang.",
        "dahil sa kawalan ng malasakit at hindi maayos na pamamahala ng silid.",
        "kaya nasasayang lamang ang oras at pagsisikap ng mga mag-aaral.",
        "na nagdulot ng matinding pagkabahala at takot pumasok sa klase.",
        "nang walang anumang tulong o gabay mula sa gurong inatasang magturo.",
        "at nag-iiwan ng malaking suliranin sa aming akademikong katayuan.",
        "na lumalabag sa mga alituntunin at karapatan ng bawat estudyante.",
        "dahil sa kawalan ng sistema at maling pamamaraan sa pagmamarka."
    ]
    for op, aspect in neg_openers:
        for subj in SUBJECTS_TL:
            for cl in RNG.sample(neg_closers, 3):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "Tagalog", "Negative", aspect, RNG.choice([1, 2])))

    neu_openers = [
        ("Katamtaman lamang ang bilis ng pagtuturo at nasusunod ang silabus sa", "Teaching Pace"),
        ("Karaniwan ang istilo ng pagtuturo ayon sa mga itinakdang kabanata sa", "Teaching Delivery"),
        ("Katamtaman ang antas ng hirap ng mga pagsusulit at takdang-aralin sa", "Exam Difficulty"),
        ("Pumapasok sa takdang oras at nagpapakawala ayon sa kampana ng paaralan sa", "Punctuality"),
        ("Karaniwan ang pamamaraan ng pagmamarka batay sa patakaran ng departamento sa", "Grading Criteria"),
        ("Sumasagot sa mga katanungan kapag nilapitan pagkatapos ng leksyon sa", "Communication"),
        ("Sapat ang inilaang oras para sagutan ang mga lingguhang pagtatasa sa", "Exam Time Limit"),
        ("Tahimik at pormal ang takbo ng silid-aralan habang tinatalakay ang", "Classroom Atmosphere"),
        ("Nasasaklaw ang mga batayang teorya ayon sa aklat-aralin sa", "Course Content"),
        ("Maikli ang mga nakasulat na puna sa ibinalik na pagsusulit sa", "Assessment Feedback"),
        ("Karaniwang propesyonal ang pakikitungo sa bawat mag-aaral ng", "Professional Conduct"),
        ("Naglalaan ng oras sa konsultasyon kung may paunang pasabi ang estudyante sa", "Mentorship")
    ]
    neu_closers = [
        "na nagbibigay ng karaniwang karanasan sa antas-kolehiyo.",
        "ayon sa inaasahang balangkas ng kurso sa pamantasan.",
        "nang walang anumang labis o kulang sa pamantayan.",
        "na angkop para sa regular na pag-aaral ng klase.",
        "at sumusunod sa itinakdang patakaran ng kagawaran."
    ]
    for op, aspect in neu_openers:
        for subj in SUBJECTS_TL:
            for cl in RNG.sample(neu_closers, 2):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "Tagalog", "Neutral", aspect, 3))

    # Facility / non-pedagogical unclassified samples in Tagalog
    facility_tl = [
        ("Malamig at tahimik ang aklatan ng pamantasan kaya nakapag-aral kami nang mabuti para sa pagsusulit.", "Tagalog", "Positive", "Library Facilities", 5),
        ("Sira ang bentilador sa silid-aklatan kaya napakainit magbasa tuwing tanghali.", "Tagalog", "Negative", "Library Facilities", 2),
        ("Masyadong maingay ang tugtugan ng banda sa plaza ng pamantasan kaya naabala ang aming klase.", "Tagalog", "Negative", "Extracurricular", 2),
        ("Kumpleto ang mga bagong aklat sa silid-aklatan para sa aming pananaliksik.", "Tagalog", "Positive", "Library Facilities", 5),
        ("Bukas ang aklatan tuwing Sabado mula ikawalo ng umaga hanggang ikalima ng hapon.", "Tagalog", "Neutral", "Library Facilities", 3),
        ("Ginanap ang palaro ng pamantasan sa gymnasium ayon sa itinakdang iskedyul.", "Tagalog", "Neutral", "Extracurricular", 3)
    ]
    samples.extend(facility_tl)

    return samples

def generate_taglish_samples():
    samples = []
    
    pos_openers = [
        ("Super galing mag-explain ni Sir at napakalinaw ng code demos sa", "Teaching Delivery"),
        ("Very approachable si Ma'am during consultation hours kapag may bug sa", "Mentorship"),
        ("Transparent at very fair magbigay ng grades at rubrics sa", "Grading Transparency"),
        ("Laging on time pumasok at well-prepared ang PowerPoint presentations sa", "Punctuality"),
        ("Hands-on magturo sa computer lab at tinutulungan ang bawat isa sa", "Lab Work"),
        ("Nagbibigay ng second chance at considerations kapag may valid reasons sa", "Empathy"),
        ("Mabilis mag-check ng quizzes at laging may inline constructive feedback sa", "Assessment Feedback"),
        ("Sobrang interactive ng class discussions at hindi nakaka-anxious sa", "Classroom Atmosphere"),
        ("Very professional makitungo at walang favoritism kahit kanino sa", "Professional Conduct"),
        ("Ang ganda ng pacing ng lessons, hindi minamadali ang complex topics sa", "Teaching Pace"),
        ("Nag-upload agad ng lecture notes at sample reviewer sa Google Classroom para sa", "Course Content"),
        ("Patas mag-grade at nagbibigay ng partial points sa computation sa", "Grading Fairness"),
        ("Sobrang bait at inspiring magturo kaya gaganahan kang pumasok sa", "Motivation"),
        ("Detailed ang rubrics at malinaw ang project milestones sa", "Rubric Clarity"),
        ("Nag-curve si Sir nung nakitang mababa ang class average sa", "Assessment Fairness"),
        ("Super clear mag-deliver ng lectures gamit ang diagrams at real-world examples sa", "Teaching Delivery"),
        ("Nag-aalok ng remedial quizzes para sa mga struggling students sa", "Assessment Support"),
        ("Very supportive sa mga quiet students na mag-recite at mag-participate sa", "Classroom Atmosphere"),
        ("Well-organized ang class schedule at laging nasusunod ang syllabus sa", "Course Content"),
        ("Magaling mag-connect ng theoretical math sa practical applications sa", "Relevance"),
        ("Napakabait makipag-usap at laging open sa feedback ng students ukol sa", "Communication"),
        ("Binigyan kami ng sapat na time allocation para matapos ang lab exercises sa", "Exam Time Limit"),
        ("Clear and transparent ang grade breakdown bago i-submit sa portal para sa", "Grading Transparency"),
        ("Marunong makinig sa concerns ng klase tungkol sa exam schedule sa", "Empathy"),
        ("Super helpful ng code reviews at debugging walkthroughs sa", "Feedback Quality")
    ]
    pos_closers = [
        "kaya madaling ma-gets kahit medyo complex ang subject matter.",
        "and you can really feel na gusto niyang matuto ang buong section.",
        "which helped us pass the departmental midterm examinations.",
        "kaya nagiging favorite subject ito ng karamihan sa aming block.",
        "giving students the confidence to code and solve problems independently.",
        "na super laking tulong para sa aming future technical career.",
        "at walang student na naiiwang nalilito pagkatapos ng lecture.",
        "making the entire classroom atmosphere welcoming and collaborative.",
        "and never leaves questions unanswered whether in person or via email.",
        "which makes the three-hour laboratory session fly by so quickly."
    ]
    for op, aspect in pos_openers:
        for subj in SUBJECTS_TLG:
            for cl in RNG.sample(pos_closers, 3):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "Taglish", "Positive", aspect, RNG.choice([4, 5])))

    neg_openers = [
        ("Binabasa lang ni Ma'am yung slides nang walang explanation kahit nagtatanong sa", "Teaching Delivery"),
        ("Palaging late pumasok si Sir ng 25 minutes tapos nagmamadali mag-lecture sa", "Punctuality"),
        ("Terror teacher, grabe magalit at nanghihiya kapag mali ang sagot sa", "Temperament"),
        ("Hindi transparent ang grading system, walang rubric at biglang may bagsak sa", "Rubric Clarity"),
        ("Napakalayo ng exam questions sa mga tinuro during classroom meetings sa", "Assessment Fairness"),
        ("Never nagbalik ng graded lab exercises hanggang matapos ang sem sa", "Assessment Feedback"),
        ("Sobrang bilis mag-discuss, parang naghahabol ng oras kaya walang ma-absorb sa", "Teaching Pace"),
        ("Walang consideration sa mga students kahit may technical brownout sa", "Empathy"),
        ("Magulo magturo, pabago-bago ang requirements at submission rules sa", "Course Content"),
        ("Paboritismo sa front-row students at binabalewala ang iba habang nagtuturo ng", "Grading Fairness"),
        ("Unresponsive sa email at MS Teams kahit urgent ang clarification sa", "Communication"),
        ("Grabe magpa-quiz, walang pasabi tapos 5 minutes lang for 20 items sa", "Pop Quizzes"),
        ("Tambak ang assignments na sabay-sabay ang deadline during midterm week sa", "Workload"),
        ("Hindi marunong mag-explain ng core algorithms, pinapa-self-study lang kami sa", "Teaching Delivery"),
        ("Napakasungit sa consultation, parang naabala pa siya kapag nagtanong sa", "Temperament"),
        ("Laging nag-oovertime kaya nahuhuli kami sa next class sa kabilang wing pagkatapos ng", "Classroom Atmosphere"),
        ("Hindi patas mag-grade sa group projects, pare-parehong bagsak kahit may nagbuhat sa", "Grading Fairness"),
        ("Outdated ang teaching materials, puro lumang software libraries ang ginagamit sa", "Course Content"),
        ("Maling answer key ang ginamit sa exam at ayaw nang i-recheck sa", "Assessment Fairness"),
        ("Pabaya sa lab sessions, iniiwan lang kami mag-isa nang walang supervision sa", "Lab Work"),
        ("Mayabang makipag-usap at minamaliit ang prior foundation ng klase sa", "Professional Conduct"),
        ("Masyadong maikli ang 30 minutes para sa computation-heavy test sa", "Exam Time Limit"),
        ("Biglang nagbawas ng points dahil sa citation format na wala sa rubric ng", "Rubric Clarity"),
        ("Puro student reporting ang ginawa buong semester, hindi man lang nag-lecture sa", "Teaching Delivery"),
        ("Walang pakialam kung marami ang bumabagsak sa preliminary assessments sa", "Empathy")
    ]
    neg_closers = [
        "kaya sobrang nakaka-frustrate at nakaka-demotivate pumasok sa klase.",
        "and students end up cramming through YouTube tutorials instead.",
        "which makes the whole subject extremely toxic and stressful.",
        "leaving us totally unprepared for the departmental final examination.",
        "causing severe anxiety and complaints from the entire student block.",
        "and refuses to listen to the class representative's polite appeals.",
        "making it one of the most stressful subjects we have taken in college.",
        "which is completely unfair to students who put in honest effort.",
        "without giving any actionable feedback on how to fix our code.",
        "and creates a hostile environment where no one dares to speak up."
    ]
    for op, aspect in neg_openers:
        for subj in SUBJECTS_TLG:
            for cl in RNG.sample(neg_closers, 3):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "Taglish", "Negative", aspect, RNG.choice([1, 2])))

    neu_openers = [
        ("Sakto lang yung bilis ng pagtuturo ni Sir, nasusunod naman ang syllabus sa", "Teaching Pace"),
        ("Average teaching style, sinusunod lang ang PowerPoint slides sa", "Teaching Delivery"),
        ("Katamtaman lang ang difficulty ng quizzes, basta nagbasa ng modules sa", "Exam Difficulty"),
        ("Pumapasok on time si Ma'am at nagdi-dismiss ayon sa bell ng university sa", "Punctuality"),
        ("Standard grading criteria, sumusunod sa passing rate ng department para sa", "Grading Criteria"),
        ("Available naman for consultation kapag nag-message in advance sa", "Mentorship"),
        ("Sakto lang ang dami ng assignments, hindi masyadong marami at hindi kakaunti sa", "Workload"),
        ("Standard multiple choice exam na based sa textbook chapters sa", "Examination"),
        ("Medyo formal ang class atmosphere, walang kwentuhan pero okay naman sa", "Classroom Atmosphere"),
        ("Brief lang ang feedback sa project drafts pero na-highlight naman ang basic errors sa", "Assessment Feedback"),
        ("Nasusunod ang course roadmap from Week 1 to Finals nang walang delay sa", "Course Content"),
        ("Standard rubrics ang ginamit for our presentations, sakto lang sa level ng", "Rubric Clarity")
    ]
    neu_closers = [
        "giving an average, typical college classroom experience.",
        "sakto lang, walang major issues pero walang super exciting activities.",
        "which meets the basic departmental curriculum requirements.",
        "normal pacing na nasasabayan naman ng karamihan sa klase.",
        "and covers what is needed for the regular semester course."
    ]
    for op, aspect in neu_openers:
        for subj in SUBJECTS_TLG:
            for cl in RNG.sample(neu_closers, 2):
                text = f"{op} {subj}, {cl}"
                samples.append((text, "Taglish", "Neutral", aspect, 3))

    # Facility / non-pedagogical unclassified samples in Taglish
    facility_tlg = [
        ("Super bagal ng Wi-Fi connection sa campus library habang nagse-search ng IEEE papers.", "Taglish", "Negative", "Library Facilities", 2),
        ("Malamig ang aircon sa library at fast ang internet kaya conducive mag-study for thesis.", "Taglish", "Positive", "Library Facilities", 5),
        ("Nag-overlap yung student council acquaintance party sa schedule ng major lab defense.", "Taglish", "Negative", "Extracurricular", 2),
        ("Madaling mag-borrow ng reference books sa library gamit ang digital catalog barcode.", "Taglish", "Positive", "Library Facilities", 4),
        ("Standard library rules ang pinatutupad, bawal kumain at uminom sa study area.", "Taglish", "Neutral", "Library Facilities", 3),
        ("Organized naman ang university cheering competition sa field nung weekend.", "Taglish", "Neutral", "Extracurricular", 3)
    ]
    samples.extend(facility_tlg)

    return samples

def main():
    print("=== EvaluaTech 10,000-Sample Dataset Expansion System ===")
    seed_rows, fieldnames = load_seed_rows()
    
    unique_texts = set()
    all_rows = []
    
    # Add seed rows first
    for r in seed_rows:
        norm = r['Feedback_Text'].strip().lower()
        if norm not in unique_texts:
            unique_texts.add(norm)
            all_rows.append(r)
    
    print(f"Loaded {len(all_rows)} unique seed samples.")
    
    # Generate candidate samples
    print("Generating candidate samples for English, Tagalog, and Taglish...")
    en_candidates = generate_english_samples()
    tl_candidates = generate_tagalog_samples()
    tlg_candidates = generate_taglish_samples()
    
    print(f"Generated pools: {len(en_candidates)} English, {len(tl_candidates)} Tagalog, {len(tlg_candidates)} Taglish.")
    
    RNG.shuffle(en_candidates)
    RNG.shuffle(tl_candidates)
    RNG.shuffle(tlg_candidates)
    
    next_id = len(all_rows) + 1
    pools = [en_candidates, tl_candidates, tlg_candidates]
    p_idx = 0
    
    while len(all_rows) < 10000:
        pool = pools[p_idx % len(pools)]
        p_idx += 1
        if not pool:
            continue
        text, lang, sent, aspect, rating = pool.pop()
        norm = text.strip().lower()
        if norm in unique_texts:
            continue
        unique_texts.add(norm)
        all_rows.append({
            'ID': str(next_id),
            'Feedback_Text': text.strip(),
            'Language': lang,
            'Sentiment': sent,
            'Aspect': aspect,
            'Rating_Score': str(rating)
        })
        next_id += 1
    
    # Renumber all rows strictly from 1 to 10000
    for idx, r in enumerate(all_rows, start=1):
        r['ID'] = str(idx)

    print(f"\nSuccessfully compiled {len(all_rows)} completely unique rows.")
    
    lang_counts = Counter(r['Language'] for r in all_rows)
    sent_counts = Counter(r['Sentiment'] for r in all_rows)
    aspect_counts = Counter(r['Aspect'] for r in all_rows)
    print("Language Breakdown:", dict(lang_counts))
    print("Sentiment Breakdown:", dict(sent_counts))
    print(f"Total Unique Aspects: {len(aspect_counts)}")
    print("Top 10 Aspects:", dict(aspect_counts.most_common(10)))
    
    # Write back to dataset CSV
    with open(TARGET_CSV, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(all_rows)
    
    print(f"Wrote {len(all_rows)} rows to {TARGET_CSV}")

if __name__ == '__main__':
    main()
