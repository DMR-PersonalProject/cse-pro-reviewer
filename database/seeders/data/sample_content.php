<?php

/*
 * Original sample items for the "Original — seed" source (FR-Q7, M2-16). Written for this project;
 * not copied from any reviewer. Numbers in tables are fictional. `answer` is the 1-based position
 * of the correct choice. `figure` names a PNG that SampleContentSeeder draws.
 */

return [
    'passages' => [
        [
            'topic' => 'reading_comprehension',
            'title' => 'Rainwater for San Isidro',
            'body' => <<<'MD'
In the dry months, the barangay of San Isidro used to rely on a single deep well. Long lines formed every morning, and some families walked more than a kilometer to fetch water. In 2019, the barangay council started a rainwater harvesting program. Each household received a 200-liter drum and gutters to collect rain from its roof. The council also trained volunteers to clean the drums and check them for mosquito larvae every week.

Two years later, the lines at the well had become much shorter. Families used the stored rainwater for washing and gardening and saved the well water for drinking and cooking. Health workers noted that dengue cases did not rise, which they credited to the weekly checks. The council now plans to add larger shared tanks near the school and the health center.
MD,
            'items' => [
                [
                    'difficulty' => 'easy',
                    'stem' => 'What was the main purpose of the rainwater harvesting program?',
                    'choices' => [
                        'To reduce the households\' dependence on the single deep well',
                        'To replace the well with a water refilling station',
                        'To supply drinking water to the school',
                        'To stop dengue in the barangay',
                    ],
                    'answer' => 1,
                    'explanation' => 'The passage opens with the problem (one well, long lines, long walks) and the drums let families use rainwater for washing and gardening, so they needed the well less.',
                ],
                [
                    'difficulty' => 'easy',
                    'stem' => 'Why did the volunteers check the drums every week?',
                    'choices' => [
                        'To measure how much rain had fallen',
                        'To keep mosquitoes from breeding in the stored water',
                        'To collect fees from the households',
                        'To find out which families used the most water',
                    ],
                    'answer' => 2,
                    'explanation' => 'The volunteers checked for mosquito larvae, and health workers credited these checks for dengue cases not rising.',
                ],
                [
                    'difficulty' => 'medium',
                    'stem' => 'Which statement can be inferred from the passage?',
                    'choices' => [
                        'Families stopped using the well entirely.',
                        'The program began because of a dengue outbreak.',
                        'The council considers the program successful enough to expand.',
                        'Each household received two drums.',
                    ],
                    'answer' => 3,
                    'explanation' => 'The council plans to add larger shared tanks, which suggests it sees the program as a success. Families still used the well for drinking and cooking, no outbreak is mentioned, and each household got one drum.',
                ],
            ],
        ],
    ],

    'items' => [
        // Verbal: word meaning (English and Filipino)
        [
            'topic' => 'word_meaning',
            'difficulty' => 'easy',
            'stem' => 'Choose the word closest in meaning to **METICULOUS**.',
            'choices' => ['Careless', 'Very careful about details', 'Quick', 'Generous'],
            'answer' => 2,
            'explanation' => 'Meticulous describes someone who pays close attention to every detail.',
        ],
        [
            'topic' => 'word_meaning',
            'difficulty' => 'easy',
            'stem' => 'Choose the word most nearly **opposite** in meaning to **SCARCE**.',
            'choices' => ['Rare', 'Costly', 'Plentiful', 'Hidden'],
            'answer' => 3,
            'explanation' => 'Scarce means in short supply; its opposite is plentiful. "Rare" is a synonym, not an antonym.',
        ],
        [
            'topic' => 'word_meaning',
            'language' => 'fil',
            'difficulty' => 'easy',
            'stem' => 'Piliin ang salitang kasingkahulugan ng **MAPAGKUMBABA**.',
            'choices' => ['Mayabang', 'Mababang-loob', 'Masipag', 'Matapang'],
            'answer' => 2,
            'explanation' => 'Ang taong mapagkumbaba ay hindi nagmamataas sa kapwa. Kasingkahulugan nito ang mababang-loob. Ang mayabang ay kasalungat nito.',
        ],
        [
            'topic' => 'word_meaning',
            'language' => 'fil',
            'difficulty' => 'medium',
            'stem' => 'Ano ang kahulugan ng salitang **MASINOP**?',
            'choices' => [
                'Maingat at maayos sa paggamit ng gamit at pera',
                'Mahilig magsalita',
                'Padalos-dalos sa pagpapasya',
                'Madaling makalimot',
            ],
            'answer' => 1,
            'explanation' => 'Ang masinop ay maingat, matipid at maayos sa pag-iingat ng kanyang mga gamit at salapi.',
        ],

        // Verbal: sentence completion
        [
            'topic' => 'sentence_completion',
            'difficulty' => 'easy',
            'stem' => 'The committee will not approve the budget ______ the missing receipts are submitted.',
            'choices' => ['because', 'unless', 'although', 'so that'],
            'answer' => 2,
            'explanation' => '"Unless" introduces the condition that must be met: no approval until the receipts arrive.',
        ],
        [
            'topic' => 'sentence_completion',
            'difficulty' => 'medium',
            'stem' => 'Neither the manager nor the clerks ______ aware of the new policy.',
            'choices' => ['was', 'is', 'has been', 'were'],
            'answer' => 4,
            'explanation' => 'With "neither ... nor", the verb agrees with the nearer subject. "Clerks" is plural, so the verb is "were".',
        ],

        // Verbal: error recognition
        [
            'topic' => 'error_recognition',
            'difficulty' => 'medium',
            'lock_choice_order' => true,
            'stem' => "Which part of the sentence contains an error?\n\n**(A)** Each of the applicants **(B)** were asked to bring **(C)** two valid IDs to the testing center.",
            'choices' => ['(A)', '(B)', '(C)', 'No error'],
            'answer' => 2,
            'explanation' => '"Each" is singular, so the verb must be singular: "Each of the applicants was asked."',
        ],
        [
            'topic' => 'error_recognition',
            'difficulty' => 'medium',
            'lock_choice_order' => true,
            'stem' => "Which part of the sentence contains an error?\n\n**(A)** She has lived in Baguio **(B)** since five years **(C)** and still enjoys the cool weather.",
            'choices' => ['(A)', '(B)', '(C)', 'No error'],
            'answer' => 2,
            'explanation' => 'A length of time takes "for", not "since": "for five years". "Since" goes with a starting point, such as "since 2020".',
        ],

        // Verbal: sentence structure
        [
            'topic' => 'sentence_structure',
            'difficulty' => 'medium',
            'stem' => 'Which sentence is correctly constructed?',
            'choices' => [
                'Walking to the office, the rain soaked my documents.',
                'While I was walking to the office, the rain soaked my documents.',
                'The rain soaked my documents walking to the office.',
                'Walking to the office, my documents were soaked by the rain.',
            ],
            'answer' => 2,
            'explanation' => 'The other choices have a dangling modifier: they make the rain or the documents do the walking. Only choice B says clearly who was walking.',
        ],
        [
            'topic' => 'sentence_structure',
            'difficulty' => 'easy',
            'stem' => 'Which of the following is a complete sentence?',
            'choices' => [
                'Because the forms were incomplete.',
                'The forms that were incomplete.',
                'Returning the incomplete forms to the applicant.',
                'The clerk returned the incomplete forms.',
            ],
            'answer' => 4,
            'explanation' => 'Only choice D has a subject (the clerk) and a main verb (returned) and expresses a complete thought. The others are fragments.',
        ],

        // Verbal: paragraph organization
        [
            'topic' => 'paragraph_organization',
            'difficulty' => 'easy',
            'stem' => "Arrange the sentences to form a logical paragraph.\n\n1. She opened it and found a letter from her grandmother.\n2. When Ana got home, she saw a small package on the table.\n3. The letter asked her to visit the province during the holidays.\n4. She smiled and began planning the trip.",
            'choices' => ['2-1-3-4', '1-2-3-4', '2-3-1-4', '4-2-1-3'],
            'answer' => 1,
            'explanation' => 'Ana first sees the package (2), opens it (1), reads what the letter asks (3), then reacts and plans (4).',
        ],
        [
            'topic' => 'paragraph_organization',
            'difficulty' => 'easy',
            'stem' => "Which sentence does NOT belong in the paragraph?\n\n(1) Mangroves protect coastal communities from storm surges. (2) Their roots trap sediment and slow down waves. (3) Mangrove forests also serve as nurseries for many kinds of fish. (4) Mount Apo is the highest mountain in the Philippines.",
            'choices' => ['Sentence 1', 'Sentence 2', 'Sentence 3', 'Sentence 4'],
            'answer' => 4,
            'lock_choice_order' => true,
            'explanation' => 'The paragraph is about what mangroves do. Sentence 4 is true but has nothing to do with mangroves.',
        ],

        // Numerical: basic operations
        [
            'topic' => 'basic_operations',
            'difficulty' => 'easy',
            'stem' => 'Compute: 3/4 + 5/8',
            'choices' => ['1 3/8', '1 1/4', '8/12', '15/32'],
            'answer' => 1,
            'explanation' => '3/4 = 6/8, and 6/8 + 5/8 = 11/8 = 1 3/8.',
        ],
        [
            'topic' => 'basic_operations',
            'difficulty' => 'easy',
            'stem' => 'What is 15% of 2,400?',
            'choices' => ['240', '360', '160', '3,600'],
            'answer' => 2,
            'explanation' => '10% of 2,400 is 240 and 5% is 120, so 15% is 240 + 120 = 360.',
        ],
        [
            'topic' => 'basic_operations',
            'difficulty' => 'medium',
            'stem' => 'Evaluate: −8 + 12 ÷ 4 × 2',
            'choices' => ['2', '−5', '−2', '−6.5'],
            'answer' => 3,
            'explanation' => 'Division and multiplication come first, left to right: 12 ÷ 4 = 3, then 3 × 2 = 6. Then −8 + 6 = −2.',
        ],

        // Numerical: number sequences
        [
            'topic' => 'number_sequences',
            'difficulty' => 'easy',
            'stem' => 'What number comes next? 2, 6, 12, 20, 30, ?',
            'choices' => ['40', '42', '44', '36'],
            'answer' => 2,
            'explanation' => 'The differences grow by 2: +4, +6, +8, +10, so the next is +12: 30 + 12 = 42.',
        ],
        [
            'topic' => 'number_sequences',
            'difficulty' => 'easy',
            'stem' => 'What number comes next? 3, 9, 27, 81, ?',
            'choices' => ['162', '108', '243', '324'],
            'answer' => 3,
            'explanation' => 'Each term is 3 times the one before: 81 × 3 = 243.',
        ],
        [
            'topic' => 'number_sequences',
            'difficulty' => 'medium',
            'stem' => 'What number comes next? 5, 10, 8, 16, 14, ?',
            'choices' => ['28', '12', '18', '30'],
            'answer' => 1,
            'explanation' => 'The pattern alternates: × 2, then − 2. After 14 comes × 2: 14 × 2 = 28.',
        ],

        // Numerical: word problems
        [
            'topic' => 'word_problems',
            'difficulty' => 'easy',
            'stem' => 'A clerk encodes 120 forms in 4 hours. At the same rate, how many forms can the clerk encode in 7 hours?',
            'choices' => ['180', '200', '210', '240'],
            'answer' => 3,
            'explanation' => 'The rate is 120 ÷ 4 = 30 forms per hour, so 7 hours give 30 × 7 = 210 forms.',
        ],
        [
            'topic' => 'word_problems',
            'difficulty' => 'medium',
            'stem' => 'A shirt priced at ₱800 is sold at a 25% discount. A 12% tax is then added to the discounted price. What is the final price?',
            'choices' => ['₱696', '₱672', '₱704', '₱600'],
            'answer' => 2,
            'explanation' => 'Discounted price: 800 × 0.75 = ₱600. With tax: 600 × 1.12 = ₱672. Subtracting 25% and adding 12% of the original price (₱696) is the common mistake.',
        ],
        [
            'topic' => 'word_problems',
            'difficulty' => 'medium',
            'stem' => 'Maria is 4 years older than Jose. In 6 years, the sum of their ages will be 40. How old is Jose now?',
            'choices' => ['10', '12', '14', '16'],
            'answer' => 2,
            'explanation' => 'Let Jose be J, so Maria is J + 4. In 6 years: (J + 6) + (J + 10) = 40, so 2J + 16 = 40 and J = 12.',
        ],

        // Analytical: word analogy
        [
            'topic' => 'word_analogy',
            'difficulty' => 'easy',
            'stem' => 'BOOK : LIBRARY :: PAINTING : ______',
            'choices' => ['Artist', 'Gallery', 'Canvas', 'Frame'],
            'answer' => 2,
            'explanation' => 'A library is where books are kept and shown to the public; a gallery is where paintings are kept and shown.',
        ],
        [
            'topic' => 'word_analogy',
            'difficulty' => 'medium',
            'stem' => 'THERMOMETER : TEMPERATURE :: ODOMETER : ______',
            'choices' => ['Speed', 'Pressure', 'Distance', 'Time'],
            'answer' => 3,
            'explanation' => 'A thermometer measures temperature; an odometer measures the distance a vehicle has traveled. Speed is measured by a speedometer.',
        ],

        // Analytical: symbolic logic and abstract reasoning
        [
            'topic' => 'abstract_reasoning',
            'difficulty' => 'easy',
            'figure' => 'rotating_arrow',
            'figure_alt' => 'Four boxes in a row. Box 1: an arrow pointing up. Box 2: an arrow pointing right. Box 3: an arrow pointing down. Box 4: a question mark.',
            'stem' => 'The figure shows a series of boxes. The arrow turns the same way from one box to the next. Which arrow belongs in the box with the question mark?',
            'choices' => ['An arrow pointing up', 'An arrow pointing right', 'An arrow pointing left', 'An arrow pointing down'],
            'answer' => 3,
            'explanation' => 'The arrow turns 90° clockwise each time: up, right, down. The next turn makes it point left.',
        ],
        [
            'topic' => 'abstract_reasoning',
            'difficulty' => 'medium',
            'stem' => 'If ■ means +, ▲ means ×, and ● means −, what is the value of 8 ▲ 3 ● 6 ■ 2?',
            'choices' => ['16', '20', '30', '−8'],
            'answer' => 2,
            'explanation' => 'The expression is 8 × 3 − 6 + 2. Multiply first: 24 − 6 + 2 = 20.',
        ],
        [
            'topic' => 'abstract_reasoning',
            'difficulty' => 'hard',
            'lock_choice_order' => true,
            'stem' => 'All inspectors are employees. Some employees are engineers. Which conclusion necessarily follows?',
            'choices' => [
                'Some inspectors are engineers.',
                'All engineers are employees.',
                'No inspector is an engineer.',
                'None of these necessarily follows.',
            ],
            'answer' => 4,
            'explanation' => 'The engineers who are employees may or may not include any inspectors, so A and C can\'t be concluded. Nothing says every engineer is an employee, so B doesn\'t follow either.',
        ],

        // Analytical: assumptions and conclusions
        [
            'topic' => 'assumptions_conclusions',
            'difficulty' => 'medium',
            'stem' => 'Statement: "Starting next year, the city will require all new buildings to install rainwater collection tanks." Which is an assumption behind this statement?',
            'choices' => [
                'All old buildings already have tanks.',
                'Collecting rainwater can help the city\'s water supply.',
                'It will not rain next year.',
                'Building owners will oppose the rule.',
            ],
            'answer' => 2,
            'explanation' => 'Requiring tanks only makes sense if the city believes rainwater collection is useful. The other choices are neither needed nor implied.',
        ],
        [
            'topic' => 'assumptions_conclusions',
            'difficulty' => 'medium',
            'stem' => 'Rule: "Applicants must pass the written exam before they can be interviewed." Ramon was interviewed. Which conclusion is valid?',
            'choices' => [
                'Ramon passed the written exam.',
                'Ramon will be hired.',
                'Ramon did not take the written exam.',
                'Everyone who passed the written exam was interviewed.',
            ],
            'answer' => 1,
            'explanation' => 'Passing the exam is required before an interview, so anyone interviewed must have passed. The rule says nothing about hiring or about every passer being interviewed.',
        ],

        // Analytical: data interpretation (fictional data)
        [
            'topic' => 'data_interpretation',
            'difficulty' => 'medium',
            'stem' => <<<'MD'
The table shows fictional exam results by region.

| Region | Examinees | Passed |
|---|---:|---:|
| NCR | 12,000 | 2,400 |
| Region III | 8,000 | 1,200 |
| Region VII | 5,000 | 1,250 |
| Region XI | 4,000 | 600 |

Which region had the highest passing rate?
MD,
            'choices' => ['NCR', 'Region III', 'Region VII', 'Region XI'],
            'answer' => 3,
            'explanation' => 'Passing rates: NCR 2,400 ÷ 12,000 = 20%; Region III 15%; Region VII 1,250 ÷ 5,000 = 25%; Region XI 15%. NCR had the most passers, but Region VII had the highest rate.',
        ],
        [
            'topic' => 'data_interpretation',
            'difficulty' => 'medium',
            'stem' => <<<'MD'
The table shows an office's fictional electricity use.

| Month | Use (kWh) |
|---|---:|
| January | 1,200 |
| February | 1,000 |
| March | 1,500 |
| April | 1,800 |

By what percent did electricity use increase from February to April?
MD,
            'choices' => ['44%', '50%', '80%', '20%'],
            'answer' => 3,
            'explanation' => 'The increase is 1,800 − 1,000 = 800 kWh. Divide by the starting value: 800 ÷ 1,000 = 80%. Dividing by the April value (44%) is the common mistake.',
        ],

        // General information: Philippine Constitution
        [
            'topic' => 'philippine_constitution',
            'difficulty' => 'easy',
            'stem' => 'Under the 1987 Constitution, what is the term of office of the President of the Philippines?',
            'choices' => [
                'Four years, with one reelection',
                'Six years, with one reelection',
                'Six years, without reelection',
                'Five years, without reelection',
            ],
            'answer' => 3,
            'explanation' => 'Article VII, Section 4: the President is elected for a term of six years and is not eligible for any reelection.',
        ],
        [
            'topic' => 'philippine_constitution',
            'difficulty' => 'easy',
            'stem' => 'Which article of the 1987 Constitution contains the Bill of Rights?',
            'choices' => ['Article II', 'Article III', 'Article VI', 'Article XIII'],
            'answer' => 2,
            'explanation' => 'The Bill of Rights is Article III. Article II covers state principles and policies, Article VI the legislative department, and Article XIII social justice and human rights.',
        ],
        [
            'topic' => 'philippine_constitution',
            'difficulty' => 'medium',
            'stem' => 'Under the 1987 Constitution, how long is a senator\'s term, and how many consecutive terms may a senator serve?',
            'choices' => [
                'Six years; no more than two consecutive terms',
                'Three years; no more than three consecutive terms',
                'Six years; no limit',
                'Four years; no more than two consecutive terms',
            ],
            'answer' => 1,
            'explanation' => 'Article VI, Section 4: senators serve six-year terms and may not serve more than two consecutive terms. Three years and three terms apply to members of the House.',
        ],

        // General information: R.A. 6713
        [
            'topic' => 'ra_6713',
            'difficulty' => 'easy',
            'stem' => 'Republic Act No. 6713 is known as the:',
            'choices' => [
                'Anti-Graft and Corrupt Practices Act',
                'Code of Conduct and Ethical Standards for Public Officials and Employees',
                'Government Procurement Reform Act',
                'Ecological Solid Waste Management Act',
            ],
            'answer' => 2,
            'explanation' => 'R.A. 6713 (1989) is the Code of Conduct and Ethical Standards for Public Officials and Employees. The Anti-Graft law is R.A. 3019 and the procurement law is R.A. 9184.',
        ],
        [
            'topic' => 'ra_6713',
            'difficulty' => 'medium',
            'stem' => 'Under R.A. 6713, within how many working days must public officials and employees respond to letters and other communications from the public?',
            'choices' => ['5', '10', '15', '30'],
            'answer' => 3,
            'explanation' => 'Section 5(a) of R.A. 6713 requires a response within fifteen (15) working days from receipt.',
        ],
        [
            'topic' => 'ra_6713',
            'difficulty' => 'easy',
            'stem' => 'Which of the following is one of the norms of conduct listed in Section 4 of R.A. 6713?',
            'choices' => ['Seniority', 'Party loyalty', 'Simple living', 'Strict confidentiality of all records'],
            'answer' => 3,
            'explanation' => 'Section 4 lists eight norms: commitment to public interest, professionalism, justness and sincerity, political neutrality, responsiveness to the public, nationalism and patriotism, commitment to democracy, and simple living.',
        ],

        // General information: peace and human rights
        [
            'topic' => 'peace_human_rights',
            'difficulty' => 'easy',
            'stem' => 'Which independent office created by the 1987 Constitution investigates human rights violations in the Philippines?',
            'choices' => ['Civil Service Commission', 'Commission on Audit', 'Commission on Human Rights', 'Office of the Solicitor General'],
            'answer' => 3,
            'explanation' => 'Article XIII, Sections 17 and 18 create the Commission on Human Rights and give it the power to investigate human rights violations.',
        ],
        [
            'topic' => 'peace_human_rights',
            'difficulty' => 'easy',
            'stem' => 'In what year did the United Nations General Assembly adopt the Universal Declaration of Human Rights?',
            'choices' => ['1945', '1948', '1966', '1987'],
            'answer' => 2,
            'explanation' => 'The UN General Assembly adopted the Universal Declaration of Human Rights on 10 December 1948, now marked as Human Rights Day.',
        ],

        // General information: environmental management and protection
        [
            'topic' => 'environment',
            'difficulty' => 'easy',
            'stem' => 'Which law is the Ecological Solid Waste Management Act of 2000?',
            'choices' => ['R.A. 9275', 'R.A. 8749', 'R.A. 9003', 'R.A. 9729'],
            'answer' => 3,
            'explanation' => 'R.A. 9003 is the Ecological Solid Waste Management Act of 2000. R.A. 9275 is the Clean Water Act, R.A. 8749 the Clean Air Act and R.A. 9729 the Climate Change Act.',
        ],
        [
            'topic' => 'environment',
            'difficulty' => 'easy',
            'stem' => 'Republic Act No. 8749 is better known as the:',
            'choices' => [
                'Philippine Clean Water Act of 2004',
                'Philippine Clean Air Act of 1999',
                'Toxic Substances and Hazardous and Nuclear Wastes Control Act',
                'Climate Change Act of 2009',
            ],
            'answer' => 2,
            'explanation' => 'R.A. 8749 is the Philippine Clean Air Act of 1999. The Clean Water Act is R.A. 9275, the toxic substances law is R.A. 6969 and the Climate Change Act is R.A. 9729.',
        ],
    ],
];
