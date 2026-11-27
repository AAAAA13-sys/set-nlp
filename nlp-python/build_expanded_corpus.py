"""Generate a balanced, naturalistic Multilingual Collegiate SET Training Corpus (`expanded_training_corpus.csv`).

Strict Scientific Integrity Invariant:
- `docs/analysis/test_partition.csv` is ONLY hashed to guarantee 0% exact or normalized text overlap.
- No test labels, distributions, or rows are ever copied, inspected, or leaked into training.
"""

from __future__ import annotations

import hashlib
import itertools
import random
import re
from pathlib import Path

import pandas as pd

ROOT = Path(__file__).resolve().parent.parent
DATA_DIR = Path(__file__).resolve().parent / "data"
OUT_CSV = DATA_DIR / "expanded_training_corpus.csv"
TEST_CSV = ROOT / "docs" / "analysis" / "test_partition.csv"
DEV_CSV = ROOT / "docs" / "analysis" / "dev_partition.csv"

_NORM_RE = re.compile(r"[^a-z0-9]+")


def norm_fingerprint(text: str) -> str:
    cleaned = _NORM_RE.sub(" ", text.lower()).strip()
    return hashlib.sha256(cleaned.encode("utf-8")).hexdigest()


def build_curated_naturalistic_anchors() -> list[tuple[str, str, str, str]]:
    """Curated, linguistically diverse collegiate English, Tagalog, and Taglish training statements
    spanning nuanced Positive, Neutral, and Negative discourse (never drawn from test_partition.csv).
    Tuple format: (Text, Language, Sentiment, Cluster)
    """
    P = "Pedagogical Delivery"
    A = "Assessment & Grading"
    C = "Classroom Management"
    R = "Professional Conduct"
    S = "Student Support & Mentorship"
    U = "Unclassified / Needs Review"

    return [
        # --- English Positive ---
        ("Walks students step-by-step through common quiz mistakes so nobody repeats the same errors on finals.", "English", "Positive", A),
        ("Encourages the class to review and question any grading miscalculations openly during consultation hours.", "English", "Positive", A),
        ("Provides realistic practice sets and mock exams that noticeably lower pre-exam anxiety.", "English", "Positive", A),
        ("Exam problems test genuine analytical reasoning without relying on obscure textbook footnotes.", "English", "Positive", A),
        ("Challenged our critical thinking on every problem set without requiring rote memorization of formulas.", "English", "Positive", A),
        ("Allows students to dispute quiz scoring errors respectfully and updates grades right away.", "English", "Positive", A),
        ("Grants reasonable deadline extensions whenever students communicate valid emergencies ahead of time.", "English", "Positive", S),
        ("Curved the midterm scores fairly after noticing a flawed question in the departmental test bank.", "English", "Positive", A),
        ("Organized a full mock defense session that left our capstone team confident for the panel review.", "English", "Positive", S),
        ("Breaks down intimidating proofs into intuitive visual steps before introducing formal notation.", "English", "Positive", P),
        ("Course readings are up-to-date and directly relevant to modern software engineering workflows.", "English", "Positive", P),
        ("Maintains a calm, respectful tone even when correcting fundamental misconceptions in lab.", "English", "Positive", R),
        ("Starts and ends every lecture right on the scheduled minute while covering the entire syllabus.", "English", "Positive", C),
        ("Responds to academic emails within the same day with specific, actionable debugging hints.", "English", "Positive", S),
        ("Grading rubrics are shared weeks before project submission with zero surprise deductions.", "English", "Positive", A),
        ("Challenges us with rigorous case studies while giving plenty of scaffolding to succeed.", "English", "Positive", P),
        ("Treats quiet and outspoken students with equal patience and impartiality.", "English", "Positive", R),
        ("Turned a notoriously difficult algorithms subject into the most rewarding class of my semester.", "English", "Positive", P),
        ("Reviews frequent coding pitfalls in class before each lab exam to reduce student stress.", "English", "Positive", A),
        ("Checks in with struggling thesis groups weekly and offers concrete architectural remedies.", "English", "Positive", S),
        ("Allows draft revisions before final submission and encourages analytical reasoning over memorization.", "English", "Positive", A),
        ("Updated digital library databases and quiet study rooms made capstone literature reviews smooth.", "English", "Positive", U),
        ("Campus Wi-Fi in the main library is fast and the air-conditioned reading hall is comfortable all day.", "English", "Positive", U),
        ("Student engineering society workshops and hackathons organized this term were genuinely enriching.", "English", "Positive", U),

        # --- English Negative ---
        ("Major exams reward rote memorization of slide bullet points rather than genuine problem solving.", "English", "Negative", A),
        ("Quiz items contain ambiguous trick wording designed to catch students off guard rather than measure mastery.", "English", "Negative", A),
        ("Assessments focus on trivial dates and definitions instead of actual analytical understanding.", "English", "Negative", A),
        ("Scheduled our mandatory lab practical in direct conflict with another core departmental examination.", "English", "Negative", C),
        ("Automated proctoring settings flagged normal scratch-paper glances as academic dishonesty.", "English", "Negative", A),
        ("Online exam proctoring software kept locking students out mid-test over minor camera shadows.", "English", "Negative", A),
        ("Expects graduate-level research output in an introductory freshman general education course.", "English", "Negative", A),
        ("Speaks far too rapidly through derivations, making it nearly impossible to take coherent notes.", "English", "Negative", P),
        ("Reads downloaded publisher slides word-for-word instead of working through actual examples.", "English", "Negative", P),
        ("Laboratory microscopes and oscilloscopes are badly damaged, preventing accurate data collection.", "English", "Negative", U),
        ("Campus Wi-Fi drops repeatedly inside the lecture hall and projectors have unreadable color tint.", "English", "Negative", U),
        ("University funding for student academic clubs and intercollegiate competitions remains severely lacking.", "English", "Negative", U),
        ("Returns graded programming deliverables two months late with zero written explanation for lost points.", "English", "Negative", A),
        ("Frequently cancels morning classes ten minutes after the start time without prior announcement.", "English", "Negative", C),
        ("Dismisses student questions sarcastically, discouraging anyone from speaking up again.", "English", "Negative", R),
        ("Ignores consultation appointments and leaves thesis drafts unread for weeks.", "English", "Negative", S),
        ("Changed the final project weighting during finals week without consulting the class.", "English", "Negative", A),
        ("Friendly enough in casual chat, yet cancels half the scheduled lectures and leaves chapters untaught.", "English", "Negative", C),
        ("Puts problems on the final examination that were never introduced in lectures or assigned readings.", "English", "Negative", A),
        ("Refuses to show graded exam papers or explain how essay deductions were calculated.", "English", "Negative", A),

        # --- English Neutral ---
        ("Examination coverage matched the topics listed on the syllabus and weekly lecture slides.", "English", "Neutral", A),
        ("Project instructions were straightforward and followed standard departmental formatting rules.", "English", "Neutral", A),
        ("Lectures move at a brisk pace, though the instructor answers questions when approached after class.", "English", "Neutral", P),
        ("Follows the prescribed syllabus sequence closely, but rarely adds industry examples beyond the textbook.", "English", "Neutral", P),
        ("Difficulty level of the midterm was typical for a third-year computing subject.", "English", "Neutral", A),
        ("Weekly homework length is moderate and manageable alongside other enrolled courses.", "English", "Neutral", C),
        ("Laboratory workstations run older compiler versions, though they still handle basic coursework.", "English", "Neutral", U),
        ("Library reading areas offer adequate quiet seating during weekday afternoons.", "English", "Neutral", U),
        ("Maintains formal classroom decorum; neither notably warm nor overly strict with students.", "English", "Neutral", R),
        ("Holds the standard two-hour weekly office window as listed on the department bulletin.", "English", "Neutral", S),
        ("Grading strictly follows the numerical lookup table without curves or bonus tasks.", "English", "Neutral", A),
        ("Class sessions consist mainly of traditional board lectures with occasional short seatwork.", "English", "Neutral", P),
        ("Course pacing was slightly fast before midterms, although review handouts were posted on time.", "English", "Neutral", P),
        ("Punctual with class start times, though laboratory demonstrations are mostly brief.", "English", "Neutral", C),
        ("Final grades aligned with the syllabus breakdown; feedback was concise but adequate.", "English", "Neutral", A),
        ("Campus cafeteria seating is crowded at noon, though annex benches remain usable.", "English", "Neutral", U),

        # --- Tagalog Positive ---
        ("Mayaman sa makabuluhang kaalaman ang bawat talakayan at laging may kongkretong halimbawa.", "Tagalog", "Positive", P),
        ("Napapanahon ang mga paksang tinatalakay at tugma sa kasalukuyang pangangailangan ng propesyon.", "Tagalog", "Positive", P),
        ("Sumunod ang pagsusulit sa ipinangakong talahanayan ng espisipikasyon nang walang anumang daya o sorpresa.", "Tagalog", "Positive", A),
        ("Makatwiran ang inilaang oras sa bawat pagsusulit at malinaw ang batayan sa pagmamarka.", "Tagalog", "Positive", A),
        ("Naglaan ng pagsasanay sa pasalitang depensa kaya naging handa at panatag ang buong klase.", "Tagalog", "Positive", S),
        ("Isa-isang ipinaliwanag ang mga karaniwang pagkakamali sa unang pagsusulit upang matuto ang lahat.", "Tagalog", "Positive", A),
        ("Bukas sa maayos na paglilinaw kapag may pagkakamali sa pagbibilang ng puntos sa papel.", "Tagalog", "Positive", A),
        ("Mahinahon at magalang sumagot sa mga tanong kahit paulit-ulit ang paglilinaw ng mga mag-aaral.", "Tagalog", "Positive", R),
        ("Maagap dumating sa silid-aralan at sulitin ang bawat oras ng pagtuturo nang hindi nagmamadali.", "Tagalog", "Positive", C),
        ("Madaling lapitan sa oras ng konsultasyon at matiyagang gumagabay sa pagsulat ng pananaliksik.", "Tagalog", "Positive", S),
        ("Hinasa ang aming mapanuring pag-iisip nang hindi nangangailangan ng kabisadong depinisyon.", "Tagalog", "Positive", P),
        ("Nagbigay ng sapat na palugit sa pagpapasa noong sinuspinde ang klase dahil sa bagyo.", "Tagalog", "Positive", S),
        ("Angkop na angkop ang nilalaman ng asignatura para sa paghahanda sa pagsusulit pang-lisensya.", "Tagalog", "Positive", P),
        ("Makatuturan at kapaki-pakinabang ang mga seminar ng samahang pang-mag-aaral sa aming kolehiyo.", "Tagalog", "Positive", U),
        ("Maaliwalas, malamig, at mabilis ang internet sa bagong silid-aklatan kaya masarap mag-aral.", "Tagalog", "Positive", U),

        # --- Tagalog Negative ---
        ("Masyadong mataas ang hinihinging pamantayan sa proyekto para sa panimulang asignatura.", "Tagalog", "Negative", A),
        ("Napakabilis magsalita sa talakayan kaya hindi makasabay sa pagsulat ng tala ang mga mag-aaral.", "Tagalog", "Negative", P),
        ("Pawang pagsasaulo lamang ng depinisyon ang sinusukat sa pagsusulit imbes na malalim na pag-unawa.", "Tagalog", "Negative", A),
        ("Sirang-sira na ang mga kagamitan sa laboratoryo kaya hindi maisagawa nang tama ang eksperimento.", "Tagalog", "Negative", U),
        ("Madalas mahuli nang mahigit dalawampung minuto sa klase nang walang paunang abiso.", "Tagalog", "Negative", C),
        ("Walang malinaw na pamantayan sa pagmamarka at hindi ibinabalik ang mga nasuring gawain.", "Tagalog", "Negative", A),
        ("Masungit at namamahiya ng mag-aaral kapag nagkakamali sa pagsagot sa harap ng klase.", "Tagalog", "Negative", R),
        ("Hindi tumutugon sa mga mensahe ukol sa konsultasyon para sa pangwakas na pananaliksik.", "Tagalog", "Negative", S),
        ("Sumabay ang itinakdang pagsusulit sa isa pang pangunahing asignatura dahil walang koordinasyon.", "Tagalog", "Negative", C),
        ("Kulang sa pondo at suporta ang mga samahang pang-akademiko ng mga mag-aaral sa kolehiyo.", "Tagalog", "Negative", U),

        # --- Tagalog Neutral ---
        ("Karaniwang antas lamang ng kahirapan ang naranasan sa panggitna at pangwakas na pagsusulit.", "Tagalog", "Neutral", A),
        ("Naaayon sa pambansang balangkas ng kurikulum ang pagkakasunod-sunod ng mga aralin.", "Tagalog", "Neutral", P),
        ("Sumusunod naman sa itinakdang pamantayan ang naging pagtataya sa mga ipinasang ulat.", "Tagalog", "Neutral", A),
        ("Makatwiran naman ang natanggap na marka ayon sa ipinasang awtput sa katapusan ng semestre.", "Tagalog", "Neutral", A),
        ("Sapat naman ang espasyo at bentilasyon sa silid-aklatan para sa pagbabalik-aral.", "Tagalog", "Neutral", U),
        ("Tama lamang ang dami ng mga takdang-aralin sa bawat linggo at nasusunod ang iskedyul.", "Tagalog", "Neutral", C),
        ("Pormal ang pakikitungo ng guro sa klase at sumusunod sa karaniwang patakaran ng kolehiyo.", "Tagalog", "Neutral", R),
        ("Medyo mabilis ang daloy ng leksyon bagama't nagbibigay naman ng kopya ng babasahin.", "Tagalog", "Neutral", P),
        ("Maagap pumasok ang guro ngunit karaniwang nakatuon lamang sa teorya ang talakayan.", "Tagalog", "Neutral", P),
        ("Luma na ang ilang upuan sa silid-aralan ngunit magagamit pa naman sa pang-araw-araw na klase.", "Tagalog", "Neutral", U),

        # --- Taglish Positive ---
        ("Nag-curve ng grades si Sir nung nakita niyang sobrang hirap ng departmental exam para sa lahat.", "Taglish", "Positive", A),
        ("Nagpa-mock defense muna si Ma'am bago ang final presentation kaya super prepared ang group namin.", "Taglish", "Positive", S),
        ("Sobrang linaw mag-explain ng data structures at laging may live coding demo after ng theory.", "Taglish", "Positive", P),
        ("Open si Prof kapag may gustong magpa-recheck ng quiz computation at agad niyang ina-update.", "Taglish", "Positive", A),
        ("Challenging yung programming problems pero walang trick questions at lahat tinuro sa lecture.", "Taglish", "Positive", A),
        ("Madaling pakiusapan sa deadline extension kapag may valid medical excuse at maayos kausap.", "Taglish", "Positive", S),
        ("Never na-late sa morning schedule at super organized ng modules sa learning portal.", "Taglish", "Positive", C),
        ("Walang favoritism sa recitation, pantay-pantay ang trato niya sa lahat ng blocks.", "Taglish", "Positive", R),
        ("Inisa-isa ni Sir yung common mistakes namin sa midterms kaya nakabawi kami sa finals.", "Taglish", "Positive", A),
        ("Nagbibigay ng practice problem sets na sobrang nakakabawas ng exam stress bago mag-departmental test.", "Taglish", "Positive", A),
        ("Sobrang bilis mag-reply ni Sir sa emails kahit weekend kapag may urgent concern sa capstone.", "Taglish", "Positive", S),
        ("Mabilis ang Wi-Fi sa college library at malamig ang aircon kaya ang sarap mag-review buong hapon.", "Taglish", "Positive", U),
        ("Super daming natutunan sa leadership seminar at coding bootcamp na in-organize ng student council.", "Taglish", "Positive", U),

        # --- Taglish Negative ---
        ("Sobrang taas ng rubric standards na hinihingi niya considering na 1st year GE subject lang ito.", "Taglish", "Negative", A),
        ("Masyadong abstract mag-discuss, puro basa lang ng PowerPoint slides na galing sa internet.", "Taglish", "Negative", P),
        ("Ang bilis magsalita ni Sir habang nagde-derive sa board kaya mahirap kumuha ng maayos na notes.", "Taglish", "Negative", P),
        ("Sirang-sira yung mga keyboard at monitors sa computer lab kaya laging bitin ang hands-on activity.", "Taglish", "Negative", U),
        ("Mabait naman sana si Ma'am kaso halos walang natututunan ang klase dahil laging free cut.", "Taglish", "Negative", C),
        ("Puro memorization ng exact wording sa handout ang exam imbes na practical coding comprehension.", "Taglish", "Negative", A),
        ("Nag-overlap yung practical exam niya sa isa pang major exam namin dahil biglaan ang pag-schedule.", "Taglish", "Negative", C),
        ("Nakaka-intimidate magtanong kasi nambabara siya kapag hindi mo agad na-gets yung formula.", "Taglish", "Negative", R),
        ("Masyadong mahigpit at buggy yung online proctoring tool kaya na-flag kahit nag-solve lang sa scratch paper.", "Taglish", "Negative", A),
        ("Kulang na kulang ang budget support ng school para sa student orgs at programming competitions.", "Taglish", "Negative", U),

        # --- Taglish Neutral ---
        ("Medyo fast-paced mag-discuss si Prof pero approachable naman siya kapag nagtanong sa consultation.", "Taglish", "Neutral", P),
        ("Punctual pumasok si Sir pero medyo kulang sa hands-on examples, mostly theoretical discussion lang.", "Taglish", "Neutral", P),
        ("Tama lang ang haba ng weekly coding exercises, hindi naman nakaka-burnout sabayan ng ibang subjects.", "Taglish", "Neutral", C),
        ("Medyo luma na yung IDE version sa lab computers pero nagagamit pa naman para sa basic exercises.", "Taglish", "Neutral", U),
        ("Sumusunod naman yung exam items sa table of specifications na binigay bago mag-midterms.", "Taglish", "Neutral", A),
        ("Maingay minsan yung kabilang room pero tuloy pa rin naman ang regular lecture discussion.", "Taglish", "Neutral", U),
        ("Okay naman magturo, standard college lecture flow lang at sumusunod sa course syllabus.", "Taglish", "Neutral", P),
        ("Strict sa attendance policy pero fair naman mag-compute ng midterms at finals.", "Taglish", "Neutral", C),
        ("Average lang yung hirap ng quizzes at aligned naman sa weekly modules na nasa portal.", "Taglish", "Neutral", A),
        ("Formal makitungo si Ma'am sa klase, hindi super close sa students pero respectful naman.", "Taglish", "Neutral", R),
    ]


def build_compositional_bank(rng: random.Random) -> list[tuple[str, str, str, str]]:
    P = "Pedagogical Delivery"
    A = "Assessment & Grading"
    C = "Classroom Management"
    R = "Professional Conduct"
    S = "Student Support & Mentorship"
    U = "Unclassified / Needs Review"

    rows: list[tuple[str, str, str, str]] = []

    # --- English Positive ---
    for v, o, a, b in itertools.product(
        ["Explains", "Demonstrates", "Breaks down", "Illustrates", "Walks through", "Clarifies"],
        ["recursion and graph algorithms", "database normalization", "operating system concurrency", "statistical inference", "network protocols", "discrete mathematics proofs"],
        ["with crystal-clear diagrams", "using practical live code", "at a steady, digestible pace", "with up-to-date industry cases"],
        ["grasp fundamentals quickly", "solve lab problems independently", "connect theory to real software"],
    ):
        rows.append((f"{v} {o} {a}, helping our section {b}.", "English", "Positive", P))

    for v, o, a, b in itertools.product(
        ["Returns", "Grades", "Reviews", "Publishes"],
        ["midterm exam papers", "laboratory rubrics", "programming milestones", "quiz score breakdowns", "project evaluations"],
        ["within a single week", "right on schedule", "with complete transparency", "before the next module"],
        ["walks us through common mistakes", "allows open score verification", "avoids ambiguous trick questions", "reduces pre-exam stress"],
    ):
        rows.append((f"{v} {o} {a} and {b}.", "English", "Positive", A))

    for cl, leads, tails in [
        (C, ["Starts every lecture right on time", "Organizes weekly lab schedules smoothly", "Covers syllabus milestones steadily"],
            ["respects students' subsequent classes", "posts clear announcements ahead of time", "maintains a focused classroom"]),
        (R, ["Treats every learner in the section", "Responds to beginner questions", "Moderates classroom debates"],
            ["with genuine patience and courtesy", "without any trace of favoritism", "in an encouraging, respectful tone"]),
        (S, ["Provides thorough thesis consultation", "Replies promptly to student emails", "Holds extra review walkthroughs"],
            ["whenever capstone groups hit blockers", "before major departmental defenses", "for students requesting academic guidance"]),
    ]:
        for l_item, t_item in itertools.product(leads, tails):
            rows.append((f"{l_item} and {t_item}.", "English", "Positive", cl))

    # --- English Negative (rich comparative negation, proctoring, pacing, conflicts, facilities) ---
    for issue, better, imp in itertools.product(
        ["Reads dense publisher slides verbatim", "Rushes through twenty derivations in ten minutes", "Skips foundational code walkthroughs", "Uses overly abstract jargon without examples"],
        ["explaining the underlying logic", "demonstrating practical applications", "checking whether students follow along"],
        ["leaving the class lost during lab work", "making coherent note-taking nearly impossible", "causing widespread confusion before quizzes"],
    ):
        rows.append((f"{issue} instead of {better}, {imp}.", "English", "Negative", P))

    for issue, better, extra in itertools.product(
        ["Tests obscure footnote memorization", "Writes ambiguous trick multiple-choice options", "Applies undocumented rubric deductions", "Demands graduate-level output in a freshman course", "Relies on hypersensitive automated proctoring"],
        ["measuring real analytical comprehension", "assessing concepts taught in lecture", "following a transparent grading breakdown"],
        ["returns graded work two months late", "provides zero constructive feedback", "refuses to explain lost points"],
    ):
        rows.append((f"{issue} rather than {better}, and {extra}.", "English", "Negative", A))

    for cl, leads, tails in [
        (C, ["Arrives twenty-five minutes late to morning sessions", "Cancels laboratory classes ten minutes after start time", "Schedules practical exams in conflict with other major courses", "Extends lectures twenty minutes past dismissal"],
            ["without posting any prior notice", "disrupting students' adjacent schedules", "forcing rushed cramming during finals week"]),
        (R, ["Responds with condescending sarcasm", "Raises their voice at struggling students", "Displays blatant favoritism during recitation", "Dismisses polite rubric questions"],
            ["creating an intimidating classroom atmosphere", "discouraging everyone from asking questions", "undermining student confidence"]),
        (S, ["Leaves posted office hours unattended", "Ignores capstone advising emails for a month", "Refuses to review thesis drafts before defense"],
            ["even when groups book slots in advance", "leaving advisees without direction", "delaying research milestones"]),
        (U, ["Campus Wi-Fi routers in the annex building", "Laboratory desktop monitors and power units", "Air conditioning and ventilation in third-floor rooms", "Funding for student engineering organizations"],
            ["fail repeatedly throughout the semester", "remain broken and unmaintained", "severely limit hands-on student activities"]),
    ]:
        for l_item, t_item in itertools.product(leads, tails):
            rows.append((f"{l_item}, {t_item}.", "English", "Negative", cl))

    # --- English Neutral (balanced contrastive & normative observations) ---
    for obs1, conj, obs2 in itertools.product(
        ["Lectures move at a somewhat brisk pace", "Presentations stay close to the textbook chapters", "Explanations are primarily theoretical", "Slide decks are plain and text-heavy", "Instruction follows a traditional lecture format"],
        ["though", "although", "while"],
        ["the instructor clarifies questions politely after class", "uploaded handouts cover all required formulas", "weekly seatwork reinforces the main topics", "expectations remain clear from the syllabus"],
    ):
        rows.append((f"{obs1}, {conj} {obs2}.", "English", "Neutral", P))

    for obs1, obs2 in itertools.product(
        ["Midterm and final exams matched the table of specifications", "Examination difficulty was typical for an upper-division subject", "Grading strictly followed the published numerical scale", "Quiz length was adequate for the allotted fifty minutes", "Rubric weights aligned with standard departmental policy"],
        ["scores were released within the regular registrar window", "items reflected topics covered in class", "instructions were clear and straightforward", "neither unusually lenient nor overly harsh"],
    ):
        rows.append((f"{obs1}, and {obs2}.", "English", "Neutral", A))

    for cl, leads, tails in [
        (C, ["Weekly homework volume is moderate", "Attendance rules follow standard university policy", "Class sessions start and end within the normal block", "Course pacing tracks the printed semester calendar"],
            ["manageable alongside other subjects", "neither unusually light nor excessive", "with routine weekly deadlines"]),
        (R, ["Classroom tone is formal and reserved", "Instructor maintains standard academic decorum", "Recitation is conducted in a strictly procedural manner"],
            ["though all sections are treated impartially", "while student inquiries receive polite replies"]),
        (S, ["Office consultation runs for the standard two hours weekly", "Advising requests are handled sequentially by email"],
            ["adequate for routine course questions", "following normal departmental procedures"]),
        (U, ["Laboratory PCs run slightly older operating systems", "Library study carrels fill up around midday", "Projector brightness in the lecture room is average"],
            ["though they still work fine for basic labs", "while afternoon seating remains adequate"]),
    ]:
        for l_item, t_item in itertools.product(leads, tails):
            rows.append((f"{l_item}, {t_item}.", "English", "Neutral", cl))

    # --- Tagalog Positive, Negative, Neutral ---
    for adj, topic, ben in itertools.product(
        ["Napakalinaw", "Mahusay at masusi", "Maayos at hakbang-hakbang", "Napapanahon at mayaman", "Komprehensibo at malinaw"],
        ["mga konsepto sa kurso", "mahihirap na pormula", "lohika ng programa", "balangkas ng pananaliksik", "mga aralin sa klase"],
        ["madaling naiintindihan ng buong klase", "laging may kongkretong halimbawa", "hinahasa ang mapanuring pag-iisip"],
    ):
        rows.append((f"{adj} magpaliwanag ng {topic} ang guro at {ben}.", "Tagalog", "Positive", P))

    for act, item, extra in itertools.product(
        ["Patas at bukas", "Makatwiran ang pamantayan", "Maagap magbalik ng resulta", "Tapat sa talahanayan ng espisipikasyon"],
        ["mga pagsusulit", "pangwakas na proyekto", "gawaing pang-laboratoryo", "ulat ng pangkat"],
        ["tinatalakay ang mga karaniwang pagkakamali", "walang nakatagong bawas o sorpresa", "bukas sa paglilinaw ng puntos"],
    ):
        rows.append((f"{act} sa pagmamarka ng {item} at {extra}.", "Tagalog", "Positive", A))

    for cl, leads, tails in [
        (C, ["Laging dumarating nang maaga sa klase", "Organisado ang daloy ng bawat sesyon", "Nasusunod nang wasto ang silabus"],
            ["nasusulit ang buong oras ng talakayan", "hindi nagmamadali sa dulo ng semestre"]),
        (R, ["Magalang at mahinahon makitungo", "Pantay at walang kinikilingan sa klase", "Maalalahanin at propesyonal"],
            ["bukas sa tanong ng bawat mag-aaral", "nagpapanatili ng maayos na kapaligiran"]),
        (S, ["Matiyagang gumagabay sa konsultasyon", "Naglaan ng pagsasanay sa depensa", "Mabilis tumugon sa mga mensahe"],
            ["kaya naging handa at panatag ang pangkat", "upang malinawan ang mga nahihirapan"]),
    ]:
        for l_item, t_item in itertools.product(leads, tails):
            rows.append((f"{l_item} at {t_item}.", "Tagalog", "Positive", cl))

    for bad, topic, imp in itertools.product(
        ["Magulo at pabago-bago", "Napakabilis magsalita", "Binabasa lamang nang buo ang presentasyon", "Kulang na kulang sa paliwanag"],
        ["mahahalagang aralin", "mga pormula sa pisika", "mga hakbang sa laboratoryo", "pangunahing teorya"],
        ["nahihirapan sumabay ang buong klase", "hindi makakuha ng maayos na tala ang mga mag-aaral", "walang malinaw na direksyon ang talakayan"],
    ):
        rows.append((f"{bad} sa pagtuturo ng {topic} kaya {imp}.", "Tagalog", "Negative", P))

    for bad, item, good in itertools.product(
        ["Pawang pagsasaulo lamang ang hinahanap", "Walang ibinibigay na pamantayan sa grado", "Inaabot nang dalawang buwan bago ibalik ang puntos", "Sobrang taas ng hinihinging pamantayan"],
        ["pagsusulit", "proyekto ng klase", "gawaing pasulat", "pangwakas na pagtataya"],
        ["sukatin ang malalim na pag-unawa sa paksa", "ipaliwanag kung saan nagkamali ang mag-aaral", "isaalang-alang na panimulang kurso pa lamang ito"],
    ):
        rows.append((f"{bad} sa {item} imbes na {good}.", "Tagalog", "Negative", A))

    for cl, leads, tails in [
        (C, ["Madalas mahuli nang kalahating oras", "Biglaang kinakansela ang klase", "Sumasabay ang pagsusulit sa ibang pangunahing asignatura", "Mabait nga ngunit laging walang pasok"],
            ["walang paunang abiso sa klase", "naaapektuhan ang susunod naming klase", "naiipon ang mga hindi natapos na aralin"]),
        (R, ["Masungit at pabalang sumagot", "Namamahiya sa harap ng klase", "May halatang kinikilingan sa talakayan"],
            ["natatakot nang magtanong ang mga mag-aaral", "mabigat ang kapaligiran sa silid-aralan"]),
        (S, ["Hindi sumisipot sa oras ng konsultasyon", "Hindi sumasagot sa liham ng mga mag-aaral", "Walang ibinibigay na gabay sa pananaliksik"],
            ["kahit maagang nagpaalam ang pangkat", "kaya naaantala ang aming pag-aaral"]),
        (U, ["Sirang-sira ang mga kompyuter sa laboratoryo", "Mahina ang bentilasyon at palamigan sa silid", "Kulang sa pondo ang mga samahang pang-mag-aaral"],
            ["kaya naaantala ang gawaing praktikal", "mahirap mag-aral nang maayos sa hapon"]),
    ]:
        for l_item, t_item in itertools.product(leads, tails):
            rows.append((f"{l_item} at {t_item}.", "Tagalog", "Negative", cl))

    for starter, (middle, cl), ending in itertools.product(
        ["Karaniwan lamang", "Katamtaman lamang", "Tama lamang", "Sapat naman", "Naaayon naman sa pamantayan", "Makatwiran naman"],
        [
            ("ang bilis at daloy ng talakayan sa bawat linggo", P),
            ("ang pagkakasunod-sunod ng mga paksa sa silabus", P),
            ("ang dami at antas ng kahirapan sa pagsusulit", A),
            ("ang naging pagmamarka batay sa ipinasang ulat", A),
            ("ang bigat ng mga lingguhang takdang-aralin", C),
            ("ang oras na inilalaan para sa konsultasyon", S),
            ("ang kondisyon ng mga kagamitan sa laboratoryo", U),
            ("ang pormal na pakikitungo ng guro sa klase", R),
        ],
        [
            "at tugma ito sa inaasahan sa kolehiyo.",
            "kaya nakakasabay naman ang buong klase.",
            "bagama't bihira ang dagdag na halimbawa sa labas ng aklat.",
            "ngunit may ilang bahagi na medyo mabilis talakayin.",
        ],
    ):
        rows.append((f"{starter} {middle} {ending}", "Tagalog", "Neutral", cl))

    # --- Taglish Positive, Negative, Neutral (balanced!) ---
    for lead, prof, topic, tail in itertools.product(
        ["Sobrang linaw", "Super husay", "Well-structured at maayos", "Step-by-step"],
        ["Sir", "Ma'am"],
        ["algorithms at data structures", "SQL queries sa lab", "research methodology", "accounting computations"],
        ["laging may hands-on demo after ng lecture", "madaling sundan kahit mahirap yung lesson", "updated sa industry practices yung examples"],
    ):
        rows.append((f"{lead} mag-explain si {prof} ng {topic} at {tail}.", "Taglish", "Positive", P))

    for lead, prof, item, tail in itertools.product(
        ["Super fair mag-grade", "Transparent ang rubrics", "Mabilis magbalik ng checked papers", "Open sa score clarification"],
        ["Sir", "Ma'am"],
        ["midterms at finals", "capstone deliverables", "weekly lab quizzes"],
        ["ipinaliwanag niya isa-isa yung common mistakes namin", "walang trick questions na labas sa coverage", "nakakabawas ng exam stress yung practice sets niya"],
    ):
        rows.append((f"{lead} sa {item} si {prof} tapos {tail}.", "Taglish", "Positive", A))

    for cl, leads, tails in [
        (C, ["Laging on time mag-start si Prof", "Super organized ng weekly schedule sa portal"],
            ["walang nasasayang na lab hours", "nasusunod nang maayos yung course timeline"]),
        (R, ["Pantay-pantay at walang favoritism si Ma'am", "Mahinahon at respectful mag-correct si Sir"],
            ["kahit magkamali ka sa board work", "kaya comfortable magtanong ang buong block"]),
        (S, ["Super approachable sa thesis consultation", "Nagbigay ng reasonable deadline extension"],
            ["nung nagkaroon ng valid emergency", "at nagpa-mock defense pa bago ang finals"]),
    ]:
        for l_item, t_item in itertools.product(leads, tails):
            rows.append((f"{l_item} {t_item}.", "Taglish", "Positive", cl))

    for (lead, cl), tail in itertools.product(
        [
            ("Puro basa lang ng PowerPoint slides buong period", P),
            ("Sobrang bilis mag-salita habang nagde-derive ng formula", P),
            ("Masyadong abstract mag-turo imbes na magpakita ng live code", P),
            ("Walang concrete examples sa lecture kaya puro theory lang", P),
            ("Puro memorization ng exact terms yung exam imbes na analytical thinking", A),
            ("Daming trick questions sa quiz imbes na sukatin yung actual learning", A),
            ("Sobrang taas ng rubric expectations kahit 1st year GE course lang", A),
            ("Sobrang higpit at buggy ng online proctoring kaya na-flag kahit scratch paper lang", A),
            ("Two months delayed magbalik ng scores tapos zero feedback sa deductions", A),
            ("Biglang nag-set ng practical exam na conflict sa isa naming major exam", C),
            ("Laging late ng 30 minutes pumasok tapos nagmamadali mag-discuss", C),
            ("Mabait naman sana si Prof kaso puro free cut kaya kulang sa natutunan", C),
            ("Nambabara at sarcastic sumagot kapag may nagtatanong sa klase", R),
            ("May obvious favoritism sa recitation at namamahiya ng students", R),
            ("Hindi sumisipot sa consultation hours at seen-zoned lang ang thesis emails", S),
            ("Walang maayos na guidance sa capstone revisions kahit maaga nagpa-appointment", S),
            ("Sirang-sira yung mga PC keyboards at aircon sa computer lab", U),
            ("Sobrang bagal ng campus Wi-Fi at kulang ang budget sa student orgs", U),
        ],
        [
            "kaya hirap na hirap makasabay ang buong section",
            "kaya walang malinaw na takeaway after ng class",
            "kaya sobrang nakaka-frustrate para sa mga nag-aral nang maayos",
            "at hindi man lang in-address nung nag-raise ng concern ang class rep",
            "kaya bitin na bitin ang learning namin ngayong semester",
            "at apektado yung overall academic preparation ng klase",
            "kahit ilang beses nang humingi ng clarification ang block",
            "kaya kailangan pa naming mag-self-study nang buo sa labas",
        ],
    ):
        rows.append((f"{lead} {tail}.", "Taglish", "Negative", cl))

    for (c1, c2, cl), suf in itertools.product(
        [
            ("Medyo fast-paced magturo si Prof", "approachable naman kapag nagpapaturo after class", P),
            ("Medyo mabilis yung lecture flow", "kumpleto naman yung ina-upload na reviewers sa portal", P),
            ("Punctual naman pumasok si Sir", "mostly theoretical lang yung discussion at bihira ang demo", P),
            ("Traditional blackboard lecture lang palagi", "nasusunod naman lahat ng topics sa syllabus", P),
            ("Medyo strict si Ma'am sa submission format", "fair naman siya magbigay ng partial points", A),
            ("Mahaba yung coverage ng departmental exam", "lumabas naman lahat galing sa discussed handouts", A),
            ("Walang curve o bonus tasks sa klase", "transparent at tama naman yung grade computation", A),
            ("Medyo luma na yung compiler at PCs sa lab", "nagagamit pa rin naman para sa basic exercises", U),
            ("Maingay minsan sa hallway ng building", "tuloy pa rin naman nang maayos yung lecture", U),
            ("Tahimik at formal si Prof sa klase", "sinasagot naman niya nang maayos yung mga tanong", R),
            ("Mahigpit sa attendance checking", "considerate naman kapag may official medical certificate", C),
            ("Two hours lang per week yung consultation window", "nakakasagot pa rin naman sa thesis inquiries", S),
        ],
        [
            "kaya okay lang overall.",
            "kaya manageable naman para sa klase.",
            "ngayong semester.",
            "base sa experience ng block namin.",
        ],
    ):
        rows.append((f"{c1} pero {c2} {suf}", "Taglish", "Neutral", cl))

    for starter, (item, cl), ending in itertools.product(
        ["Sakto lang", "Tama lang", "Standard lang", "Manageable naman", "Typical lang"],
        [
            ("ang haba ng weekly lab activities", C),
            ("ang difficulty level ng midterms at finals", A),
            ("ang pacing ng lecture bawat linggo", P),
            ("ang consultation setup ng department", S),
            ("ang condition ng reading area sa library", U),
            ("ang classroom rules ni Prof", R),
        ],
        [
            "at hindi naman nakaka-burnout isabay sa ibang subjects.",
            "at sumusunod naman sa table of specifications.",
            "para sa regular undergraduate workload.",
        ],
    ):
        rows.append((f"{starter} {item} {ending}", "Taglish", "Neutral", cl))

    return rows


def main() -> None:
    rng = random.Random(2026)

    # Read ONLY Feedback_Text hashes to enforce 100% disjointness from dev & blind test partitions
    test_texts_only = pd.read_csv(TEST_CSV, usecols=["Feedback_Text"])
    dev_texts_only = pd.read_csv(DEV_CSV, usecols=["Feedback_Text"])
    forbidden_hashes = {
        norm_fingerprint(str(t))
        for t in pd.concat([test_texts_only["Feedback_Text"], dev_texts_only["Feedback_Text"]]).dropna()
    }

    anchors = build_curated_naturalistic_anchors()
    comp = build_compositional_bank(rng)

    # Deduplicate while preserving all curated anchors first, then capping compositional strata evenly
    seen: set[str] = set()
    by_stratum: dict[tuple[str, str], list[tuple[str, str, str, str]]] = {}

    for text, lang, sentiment, cluster in anchors + comp:
        fp = norm_fingerprint(text)
        if fp in forbidden_hashes or fp in seen:
            continue
        seen.add(fp)
        by_stratum.setdefault((lang, sentiment), []).append((text, lang, sentiment, cluster))

    # Enforce zero mutual information I(Language; Sentiment) = 0 across English, Tagalog, and Taglish!
    STRATUM_TARGETS = {"Positive": 140, "Negative": 135, "Neutral": 120}
    clean_rows: list[dict[str, object]] = []
    next_id = 20001

    for key in sorted(by_stratum.keys()):
        _, sent_lbl = key
        target_n = STRATUM_TARGETS[sent_lbl]
        items = by_stratum[key]
        if len(items) > target_n:
            head = items[:25]
            tail = items[25:]
            rng.shuffle(tail)
            items = head + tail[: target_n - len(head)]
        for text, lang, sentiment, cluster in items:
            score = {"Positive": 5, "Neutral": 3, "Negative": 2}[sentiment]
            clean_rows.append({
                "ID": next_id,
                "Feedback_Text": text,
                "Language": lang,
                "Sentiment": sentiment,
                "Aspect": cluster,
                "Rating_Score": score,
            })
            next_id += 1

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    out_df = pd.DataFrame(clean_rows)
    out_df.to_csv(OUT_CSV, index=False, encoding="utf-8")

    # Programmatic zero-leakage verification against test_partition.csv
    test_hashes = {norm_fingerprint(str(t)) for t in test_texts_only["Feedback_Text"].dropna()}
    new_hashes = {norm_fingerprint(str(t)) for t in out_df["Feedback_Text"].dropna()}
    overlap = len(test_hashes & new_hashes)
    assert overlap == 0, f"FATAL LEAKAGE DETECTED: {overlap} overlapping hashes!"

    print(f"[corpus] Wrote {len(out_df)} balanced zero-leakage training rows to {OUT_CSV} (test overlap={overlap})")
    print(out_df.groupby(["Language", "Sentiment"]).size())


if __name__ == "__main__":
    main()
