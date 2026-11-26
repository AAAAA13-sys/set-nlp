//! Canonical 25-Item 5-Cluster Institutional SET Instrument for School AAAAA13.

pub struct ClusterSpec {
    pub code: &'static str,
    pub name: &'static str,
    pub items: [&'static str; 5],
}

pub const FIVE_CLUSTERS: [ClusterSpec; 5] = [
    ClusterSpec {
        code: "CLUSTER-1",
        name: "Pedagogical Delivery",
        items: [
            "Explains complex concepts and algorithms clearly with structured progression.",
            "Demonstrates deep mastery of the course subject matter and current industry/academic standards.",
            "Connects theoretical foundations to practical code walkthroughs, lab work, or real-world examples.",
            "Paces lectures appropriately for student comprehension without rushing core topics.",
            "Uses instructional slides, visual aids, and demonstrations effectively to clarify difficult topics.",
        ],
    },
    ClusterSpec {
        code: "CLUSTER-2",
        name: "Assessment & Grading",
        items: [
            "Publishes transparent grading criteria and analytic rubrics prior to major deliverables.",
            "Designs examinations, quizzes, and projects that align fairly with topics covered in class.",
            "Returns graded quizzes, assignments, and project evaluations within a reasonable timeframe.",
            "Provides constructive, actionable feedback that helps learners understand mistakes and improve.",
            "Applies partial credit and grading policies consistently and impartially across all students.",
        ],
    },
    ClusterSpec {
        code: "CLUSTER-3",
        name: "Classroom Management",
        items: [
            "Starts and concludes scheduled lecture and laboratory sessions punctually.",
            "Adheres systematically to the approved departmental course syllabus and weekly schedule.",
            "Maintains an orderly, focused, and distraction-free classroom or laboratory environment.",
            "Manages instructional time effectively across lectures, interactive Q&A, and hands-on activities.",
            "Communicates schedule adjustments, deadlines, and course announcements clearly in advance.",
        ],
    },
    ClusterSpec {
        code: "CLUSTER-4",
        name: "Professional Conduct",
        items: [
            "Treats all students with respect, courtesy, and academic professionalism.",
            "Fosters an inclusive, psychologically safe learning atmosphere open to questions.",
            "Demonstrates fairness and zero favoritism in classroom interactions and evaluations.",
            "Upholds institutional ethics, academic integrity, and School AAAAA13 faculty standards.",
            "Responds to student viewpoints and inquiries with patience and composure.",
        ],
    },
    ClusterSpec {
        code: "CLUSTER-5",
        name: "Student Support & Mentorship",
        items: [
            "Maintains regular, accessible weekly consultation and academic advising hours.",
            "Responds promptly and helpfully to institutional email or course portal inquiries.",
            "Provides proactive guidance to students experiencing difficulty with course concepts.",
            "Offers constructive mentorship on capstone projects, research, and technical skill growth.",
            "Encourages student self-efficacy, independent inquiry, and continuous academic improvement.",
        ],
    },
];
