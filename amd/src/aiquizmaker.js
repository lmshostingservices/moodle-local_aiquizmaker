/**
 * AI Quiz Maker AMD module.
 *
 * @module     local_aiquizmaker/essaymaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/notification'], function($, Str, Notification) {

    var config = {};
    var criteriaIndex = 0;
    var strings = {};
    var categories = []; // Question categories for auto-create
    var lastGeneratedQuestions = []; // Store last generated questions
    var currentInputMode = 'criteria'; // 'criteria' or 'ownquestions'
    var currentHasQuizContext = false; // Whether we are in a quiz context (cmid > 0)

    /**
     * Decode HTML entities (e.g., &#039; to apostrophe).
     * @param {string} text - Text with HTML entities
     * @return {string} Decoded text
     */
    function decodeHtmlEntities(text) {
        if (!text) return '';
        var textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    // State/region mappings for each country
    var countryStates = {
        'Australia': [
            {value: 'Western Australia', label: 'Western Australia'},
            {value: 'Queensland', label: 'Queensland'},
            {value: 'New South Wales', label: 'New South Wales'},
            {value: 'Victoria', label: 'Victoria'},
            {value: 'South Australia', label: 'South Australia'},
            {value: 'Tasmania', label: 'Tasmania'},
            {value: 'Northern Territory', label: 'Northern Territory'},
            {value: 'Australian Capital Territory', label: 'ACT'}
        ],
        'New Zealand': [
            {value: 'Auckland', label: 'Auckland'},
            {value: 'Wellington', label: 'Wellington'},
            {value: 'Canterbury', label: 'Canterbury'},
            {value: 'Waikato', label: 'Waikato'},
            {value: 'Bay of Plenty', label: 'Bay of Plenty'},
            {value: 'Otago', label: 'Otago'},
            {value: 'Manawatu-Whanganui', label: 'Manawatu-Whanganui'},
            {value: 'Hawkes Bay', label: "Hawke's Bay"},
            {value: 'Taranaki', label: 'Taranaki'},
            {value: 'Southland', label: 'Southland'},
            {value: 'Northland', label: 'Northland'},
            {value: 'Gisborne', label: 'Gisborne'},
            {value: 'Nelson', label: 'Nelson'},
            {value: 'Marlborough', label: 'Marlborough'},
            {value: 'West Coast', label: 'West Coast'},
            {value: 'Tasman', label: 'Tasman'}
        ],
        'United Kingdom': [
            {value: 'England', label: 'England'},
            {value: 'Scotland', label: 'Scotland'},
            {value: 'Wales', label: 'Wales'},
            {value: 'Northern Ireland', label: 'Northern Ireland'}
        ],
        'United States': [
            {value: 'California', label: 'California'},
            {value: 'Texas', label: 'Texas'},
            {value: 'Florida', label: 'Florida'},
            {value: 'New York', label: 'New York'},
            {value: 'Pennsylvania', label: 'Pennsylvania'},
            {value: 'Illinois', label: 'Illinois'},
            {value: 'Ohio', label: 'Ohio'},
            {value: 'Georgia', label: 'Georgia'},
            {value: 'North Carolina', label: 'North Carolina'},
            {value: 'Michigan', label: 'Michigan'},
            {value: 'Washington', label: 'Washington'},
            {value: 'Arizona', label: 'Arizona'},
            {value: 'Massachusetts', label: 'Massachusetts'},
            {value: 'Colorado', label: 'Colorado'},
            {value: 'Other US State', label: 'Other'}
        ],
        'Canada': [
            {value: 'Ontario', label: 'Ontario'},
            {value: 'Quebec', label: 'Quebec'},
            {value: 'British Columbia', label: 'British Columbia'},
            {value: 'Alberta', label: 'Alberta'},
            {value: 'Manitoba', label: 'Manitoba'},
            {value: 'Saskatchewan', label: 'Saskatchewan'},
            {value: 'Nova Scotia', label: 'Nova Scotia'},
            {value: 'New Brunswick', label: 'New Brunswick'},
            {value: 'Newfoundland and Labrador', label: 'Newfoundland and Labrador'},
            {value: 'Prince Edward Island', label: 'Prince Edward Island'},
            {value: 'Northwest Territories', label: 'Northwest Territories'},
            {value: 'Yukon', label: 'Yukon'},
            {value: 'Nunavut', label: 'Nunavut'}
        ],
        'Singapore': []
    };

    // Fallback industries list
    var fallbackIndustries = [
        'Agriculture, Forestry & Fishing',
        'Mining & Resources',
        'Manufacturing',
        'Electricity, Gas, Water & Waste',
        'Construction',
        'Wholesale Trade',
        'Retail Trade',
        'Accommodation & Food Services',
        'Transport, Postal & Warehousing',
        'Information Media & Telecommunications',
        'Financial & Insurance Services',
        'Rental, Hiring & Real Estate',
        'Professional, Scientific & Technical',
        'Administrative & Support Services',
        'Public Administration & Safety',
        'Education & Training',
        'Health Care & Social Assistance',
        'Community Services',
        'Arts & Recreation Services',
        'Business Services',
        'Other Services'
    ];

    // Industry  ->  Job Level  ->  Job Title mapping for Australian WHS context
    var industryJobMatrix = {
        'Construction': {
            'Entry Level': ['Apprentice', 'Labourer', 'Trade Assistant', 'Trainee', 'Builder\'s Labourer'],
            'Intermediate': ['Carpenter', 'Electrician', 'Plumber', 'Bricklayer', 'Painter', 'Plasterer', 'Roofer', 'Concreter', 'Steel Fixer'],
            'Senior': ['Licensed Builder', 'Senior Tradesperson', 'Master Electrician', 'Lead Carpenter', 'Specialist Contractor'],
            'Supervisor': ['Site Supervisor', 'Foreman', 'Leading Hand', 'Team Leader', 'Works Supervisor'],
            'Manager': ['Project Manager', 'Construction Manager', 'WHS Manager', 'Site Manager', 'Operations Manager'],
            'Executive': ['Director', 'General Manager', 'CEO', 'Managing Director']
        },
        'Mining & Resources': {
            'Entry Level': ['Mine Worker', 'Trainee Operator', 'Process Plant Attendant', 'Utility Worker', 'Trade Assistant'],
            'Intermediate': ['Haul Truck Operator', 'Drill Operator', 'Fitter', 'Boilermaker', 'Electrician', 'Process Technician'],
            'Senior': ['Senior Operator', 'Senior Technician', 'Blaster', 'Shotfirer', 'Senior Fitter'],
            'Supervisor': ['Shift Supervisor', 'Crew Leader', 'Underground Supervisor', 'Processing Supervisor', 'Maintenance Supervisor'],
            'Manager': ['Mine Manager', 'Operations Manager', 'Processing Manager', 'WHS Manager', 'Maintenance Manager'],
            'Executive': ['General Manager', 'Director of Operations', 'CEO', 'Managing Director']
        },
        'Manufacturing': {
            'Entry Level': ['Production Worker', 'Machine Operator Trainee', 'Packer', 'Storesperson', 'Assembly Worker'],
            'Intermediate': ['Machine Operator', 'Quality Controller', 'Maintenance Technician', 'Welder', 'CNC Operator'],
            'Senior': ['Senior Operator', 'Lead Technician', 'Toolmaker', 'Process Technician', 'Senior Welder'],
            'Supervisor': ['Production Supervisor', 'Shift Supervisor', 'Line Leader', 'Quality Supervisor', 'Maintenance Supervisor'],
            'Manager': ['Production Manager', 'Plant Manager', 'WHS Manager', 'Quality Manager', 'Operations Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Transport, Postal & Warehousing': {
            'Entry Level': ['Warehouse Worker', 'Delivery Driver', 'Forklift Operator Trainee', 'Picker/Packer', 'Courier'],
            'Intermediate': ['Truck Driver', 'Forklift Operator', 'Crane Operator', 'Logistics Coordinator', 'Freight Handler'],
            'Senior': ['Heavy Vehicle Driver', 'Senior Logistics Officer', 'Transport Coordinator', 'Fleet Controller'],
            'Supervisor': ['Warehouse Supervisor', 'Transport Supervisor', 'Logistics Supervisor', 'Fleet Supervisor', 'Shift Supervisor'],
            'Manager': ['Warehouse Manager', 'Transport Manager', 'Logistics Manager', 'WHS Manager', 'Operations Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Health Care & Social Assistance': {
            'Entry Level': ['Care Assistant', 'Support Worker', 'Trainee Nurse', 'Hospital Orderly', 'Pathology Collector Trainee', 'Dental Assistant Trainee', 'Pharmacy Assistant', 'Cleaner', 'Kitchen Hand'],
            'Intermediate': ['Enrolled Nurse', 'Personal Care Worker', 'Allied Health Assistant', 'Disability Support Worker', 'Dental Assistant', 'Physiotherapy Assistant', 'Occupational Therapy Assistant', 'Pathology Collector', 'Sonographer', 'Medical Receptionist', 'Paramedic (Student)'],
            'Senior': ['Registered Nurse', 'Senior Care Worker', 'Allied Health Professional', 'Clinical Coordinator', 'Paramedic', 'Radiographer', 'Physiotherapist', 'Occupational Therapist', 'Dietitian', 'Social Worker', 'Pharmacist'],
            'Supervisor': ['Team Leader', 'Shift Supervisor', 'Clinical Supervisor', 'Ward Supervisor', 'Unit Manager', 'Nurse Unit Manager', 'Community Health Coordinator'],
            'Manager': ['Nursing Manager', 'Facility Manager', 'WHS Manager', 'Clinical Manager', 'Operations Manager', 'Hospital Administrator', 'Practice Manager', 'Allied Health Manager'],
            'Executive': ['Director of Nursing', 'Chief Medical Officer', 'CEO', 'General Manager', 'Managing Director', 'Director of Allied Health']
        },
        'Community Services': {
            'Entry Level': ['Community Services Trainee', 'Support Worker Trainee', 'Child Care Trainee', 'Youth Worker Trainee', 'Community Worker', 'Volunteer Coordinator Assistant', 'Intake Officer Assistant', 'Crisis Support Volunteer'],
            'Intermediate': ['Disability Support Worker', 'Aged Care Worker', 'Child Care Worker', 'Early Childhood Educator', 'Youth Worker', 'Family Support Worker', 'Community Service Worker', 'Mental Health Support Worker', 'Drug & Alcohol Worker', 'Social Housing Officer', 'Outreach Worker', 'Domestic Violence Support Worker', 'Counsellor (Diploma Level)', 'Recreation Officer'],
            'Senior': ['Senior Support Worker', 'Senior Child Care Worker', 'Lead Educator', 'Senior Case Worker', 'Senior Youth Worker', 'Mental Health Clinician', 'Senior Social Worker', 'Trauma-Informed Practitioner', 'Specialist Family Violence Worker', 'Homelessness Support Worker (Senior)', 'Community Development Worker'],
            'Supervisor': ['Team Leader', 'Room Leader (Child Care)', 'Case Manager', 'Program Coordinator', 'Unit Manager (Community Services)', 'Child Care Director (Assistant)', 'Senior Coordinator', 'Residential Supervisor', 'Family Services Coordinator'],
            'Manager': ['Service Manager', 'Program Manager', 'Area Manager', 'Child Care Centre Director', 'Disability Service Manager', 'WHS Manager', 'Community Services Manager', 'Aged Care Facility Manager', 'Family Services Manager', 'Housing Manager'],
            'Executive': ['CEO', 'General Manager', 'Director of Services', 'Managing Director', 'Executive Director', 'Chief Operations Officer']
        },
        'Retail Trade': {
            'Entry Level': ['Sales Assistant', 'Cashier', 'Stock Handler', 'Customer Service', 'Trainee'],
            'Intermediate': ['Senior Sales', 'Visual Merchandiser', 'Stock Controller', 'Team Member', 'Customer Service Officer'],
            'Senior': ['Department Specialist', 'Senior Sales Consultant', 'Loss Prevention Officer', 'Training Coordinator'],
            'Supervisor': ['Team Leader', 'Shift Supervisor', 'Department Supervisor', 'Assistant Manager'],
            'Manager': ['Store Manager', 'Area Manager', 'WHS Manager', 'Operations Manager', 'Regional Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Accommodation & Food Services': {
            'Entry Level': ['Kitchen Hand', 'Waiter/Waitress', 'Cleaner', 'Room Attendant', 'Food Runner'],
            'Intermediate': ['Cook', 'Barista', 'Bartender', 'Receptionist', 'Food & Beverage Attendant'],
            'Senior': ['Chef', 'Senior Cook', 'Head Waiter', 'Concierge', 'Senior Receptionist'],
            'Supervisor': ['Kitchen Supervisor', 'Restaurant Supervisor', 'Front Office Supervisor', 'Shift Supervisor'],
            'Manager': ['Restaurant Manager', 'Hotel Manager', 'WHS Manager', 'Food & Beverage Manager', 'Operations Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Agriculture, Forestry & Fishing': {
            'Entry Level': ['Farm Hand', 'Picker', 'General Labourer', 'Trainee', 'Station Hand'],
            'Intermediate': ['Machine Operator', 'Irrigator', 'Stockperson', 'Harvester Operator', 'Tractor Driver'],
            'Senior': ['Senior Farm Worker', 'Overseer', 'Head Stockperson', 'Senior Operator', 'Leading Hand'],
            'Supervisor': ['Farm Supervisor', 'Crew Leader', 'Station Supervisor', 'Production Supervisor'],
            'Manager': ['Farm Manager', 'Station Manager', 'WHS Manager', 'Operations Manager', 'Property Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Electricity, Gas, Water & Waste': {
            'Entry Level': ['Utility Worker', 'Trainee', 'Labourer', 'Meter Reader', 'Trade Assistant'],
            'Intermediate': ['Linesperson', 'Fitter', 'Electrician', 'Plumber', 'Plant Operator'],
            'Senior': ['Senior Technician', 'Senior Electrician', 'Senior Operator', 'Network Technician'],
            'Supervisor': ['Crew Supervisor', 'Shift Supervisor', 'Field Supervisor', 'Works Supervisor'],
            'Manager': ['Operations Manager', 'Network Manager', 'WHS Manager', 'Maintenance Manager', 'Project Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Education & Training': {
            'Entry Level': ['Teacher Aide', 'Trainee', 'Cleaner', 'Groundskeeper', 'Administrative Assistant'],
            'Intermediate': ['Teacher', 'Trainer', 'Assessor', 'Student Support Officer', 'IT Support'],
            'Senior': ['Senior Teacher', 'Lead Trainer', 'Curriculum Developer', 'Head of Department'],
            'Supervisor': ['Team Leader', 'Coordinator', 'Head Teacher', 'Training Supervisor'],
            'Manager': ['Principal', 'Training Manager', 'WHS Manager', 'Operations Manager', 'Campus Manager'],
            'Executive': ['Director', 'CEO', 'General Manager', 'Managing Director']
        },
        'Professional, Scientific & Technical': {
            'Entry Level': ['Graduate', 'Trainee', 'Junior Consultant', 'Administrative Assistant', 'Lab Assistant'],
            'Intermediate': ['Consultant', 'Analyst', 'Technician', 'Engineer', 'Scientist'],
            'Senior': ['Senior Consultant', 'Senior Engineer', 'Senior Analyst', 'Project Lead', 'Senior Scientist'],
            'Supervisor': ['Team Leader', 'Project Supervisor', 'Technical Lead', 'Section Leader'],
            'Manager': ['Project Manager', 'Department Manager', 'WHS Manager', 'Operations Manager', 'Technical Manager'],
            'Executive': ['Director', 'Partner', 'CEO', 'Managing Director', 'General Manager']
        },
        'Public Administration & Safety': {
            'Entry Level': ['Administrative Officer', 'Customer Service Officer', 'Trainee', 'Clerical Officer'],
            'Intermediate': ['Policy Officer', 'Project Officer', 'Compliance Officer', 'Inspector', 'Case Worker'],
            'Senior': ['Senior Officer', 'Senior Policy Analyst', 'Senior Inspector', 'Senior Case Worker'],
            'Supervisor': ['Team Leader', 'Coordinator', 'Supervisor', 'Section Leader'],
            'Manager': ['Manager', 'WHS Manager', 'Operations Manager', 'Program Manager', 'Branch Manager'],
            'Executive': ['Director', 'Executive Director', 'CEO', 'Secretary', 'General Manager']
        },
        'Wholesale Trade': {
            'Entry Level': ['Warehouse Worker', 'Picker/Packer', 'Trainee', 'Sales Assistant', 'Delivery Driver'],
            'Intermediate': ['Sales Representative', 'Account Executive', 'Forklift Operator', 'Inventory Controller', 'Customer Service Officer'],
            'Senior': ['Senior Sales Representative', 'Key Account Manager', 'Senior Buyer', 'Purchasing Officer', 'Trade Specialist'],
            'Supervisor': ['Warehouse Supervisor', 'Sales Supervisor', 'Team Leader', 'Logistics Supervisor'],
            'Manager': ['Sales Manager', 'Warehouse Manager', 'WHS Manager', 'Operations Manager', 'Regional Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Information Media & Telecommunications': {
            'Entry Level': ['Trainee', 'Junior Technician', 'Customer Service Officer', 'Administrative Assistant', 'Field Technician'],
            'Intermediate': ['Network Technician', 'IT Support Specialist', 'Telecommunications Technician', 'Systems Administrator', 'Help Desk Officer'],
            'Senior': ['Senior Network Engineer', 'Senior Systems Administrator', 'Solutions Architect', 'Senior Developer', 'Infrastructure Specialist'],
            'Supervisor': ['Team Leader', 'Technical Lead', 'Project Coordinator', 'Shift Supervisor'],
            'Manager': ['IT Manager', 'Network Manager', 'WHS Manager', 'Operations Manager', 'Project Manager'],
            'Executive': ['CTO', 'CIO', 'General Manager', 'Director', 'CEO']
        },
        'Financial & Insurance Services': {
            'Entry Level': ['Customer Service Officer', 'Administrative Assistant', 'Trainee', 'Data Entry Clerk', 'Receptionist'],
            'Intermediate': ['Financial Adviser', 'Insurance Agent', 'Loan Officer', 'Claims Officer', 'Underwriter'],
            'Senior': ['Senior Financial Adviser', 'Senior Analyst', 'Senior Underwriter', 'Portfolio Manager', 'Risk Analyst'],
            'Supervisor': ['Team Leader', 'Supervisor', 'Branch Supervisor', 'Claims Supervisor'],
            'Manager': ['Branch Manager', 'Claims Manager', 'WHS Manager', 'Operations Manager', 'Risk Manager'],
            'Executive': ['CFO', 'General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Rental, Hiring & Real Estate': {
            'Entry Level': ['Receptionist', 'Administrative Assistant', 'Trainee', 'Property Assistant', 'Customer Service Officer'],
            'Intermediate': ['Property Manager', 'Real Estate Agent', 'Leasing Consultant', 'Rental Agent', 'Valuer'],
            'Senior': ['Senior Property Manager', 'Senior Real Estate Agent', 'Senior Valuer', 'Licensed Agent', 'Portfolio Manager'],
            'Supervisor': ['Team Leader', 'Office Supervisor', 'Property Supervisor', 'Leasing Supervisor'],
            'Manager': ['Agency Principal', 'Property Manager', 'WHS Manager', 'Operations Manager', 'Regional Manager'],
            'Executive': ['Director', 'General Manager', 'CEO', 'Managing Director']
        },
        'Administrative & Support Services': {
            'Entry Level': ['Cleaner', 'Security Guard', 'Receptionist', 'Administrative Assistant', 'Data Entry Clerk'],
            'Intermediate': ['Office Administrator', 'Executive Assistant', 'Payroll Officer', 'HR Officer', 'Recruitment Consultant'],
            'Senior': ['Senior Administrator', 'Senior HR Officer', 'Senior Recruiter', 'Office Manager', 'Facilities Coordinator'],
            'Supervisor': ['Team Leader', 'Supervisor', 'Shift Supervisor', 'Security Supervisor', 'Cleaning Supervisor'],
            'Manager': ['Office Manager', 'Facilities Manager', 'WHS Manager', 'HR Manager', 'Operations Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Arts & Recreation Services': {
            'Entry Level': ['Attendant', 'Trainee', 'Customer Service Officer', 'Cleaner', 'Lifeguard'],
            'Intermediate': ['Fitness Instructor', 'Personal Trainer', 'Recreation Officer', 'Event Coordinator', 'Sports Coach'],
            'Senior': ['Senior Instructor', 'Head Coach', 'Senior Event Coordinator', 'Program Coordinator', 'Facility Coordinator'],
            'Supervisor': ['Team Leader', 'Shift Supervisor', 'Duty Manager', 'Program Supervisor'],
            'Manager': ['Facility Manager', 'Recreation Manager', 'WHS Manager', 'Events Manager', 'Operations Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        },
        'Business Services': {
            'Entry Level': ['Administrative Assistant', 'Receptionist', 'Trainee', 'Data Entry Clerk', 'Office Junior'],
            'Intermediate': ['Business Analyst', 'Account Manager', 'Marketing Coordinator', 'Project Coordinator', 'Client Services Officer'],
            'Senior': ['Senior Business Analyst', 'Senior Account Manager', 'Senior Consultant', 'Business Development Manager', 'Senior Project Officer'],
            'Supervisor': ['Team Leader', 'Operations Supervisor', 'Client Services Supervisor', 'Project Supervisor'],
            'Manager': ['Operations Manager', 'Business Development Manager', 'WHS Manager', 'Client Services Manager', 'General Manager'],
            'Executive': ['Director', 'CEO', 'Managing Director', 'Chief Operating Officer', 'General Manager']
        },
        'Other Services': {
            'Entry Level': ['Trainee', 'Assistant', 'Labourer', 'Cleaner', 'Customer Service Officer'],
            'Intermediate': ['Technician', 'Tradesperson', 'Service Officer', 'Specialist', 'Coordinator'],
            'Senior': ['Senior Technician', 'Lead Specialist', 'Senior Coordinator', 'Master Tradesperson'],
            'Supervisor': ['Team Leader', 'Supervisor', 'Shift Supervisor', 'Site Supervisor'],
            'Manager': ['Operations Manager', 'Service Manager', 'WHS Manager', 'Branch Manager', 'Regional Manager'],
            'Executive': ['General Manager', 'Director', 'CEO', 'Managing Director']
        }
    };

    // Default job levels (used when industry not in matrix)
    var defaultJobLevels = ['Entry Level', 'Intermediate', 'Senior', 'Supervisor', 'Manager', 'Executive'];

    /**
     * Log a message to the console.
     * @param {string} message The message to log.
     * @param {*} data Optional data to log.
     */
    function log(message, data) {
        if (window.console && window.console.log) {
            window.console.log('[AI Quiz Maker] ' + message, data || '');
        }
    }

    /**
     * Show or hide the loading overlay.
     * @param {boolean} show Whether to show the overlay.
     * @param {string} text Optional loading text.
     */
    function showLoading(show, text) {
        var overlay = $('#aiquizmaker-loading');
        if (show) {
            overlay.find('.aiquizmaker-loading-text').text(text || strings.processing);
            overlay.fadeIn(200);
        } else {
            overlay.fadeOut(200);
        }
    }

    /**
     * Show an alert message.
     * @param {string} type Alert type (error, success, warning).
     * @param {string} title Alert title.
     * @param {string} message Alert message.
     */
    function showAlert(type, title, message) {
        $('.aiquizmaker-alert').remove();

        var iconPath = '';
        if (type === 'error') {
            iconPath = '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>';
        } else if (type === 'success') {
            iconPath = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
        } else {
            iconPath = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
        }

        var alertHtml = '<div class="aiquizmaker-alert aiquizmaker-alert-' + type + '" style="margin-bottom: 16px;">' +
            '<svg class="aiquizmaker-alert-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            iconPath + '</svg>' +
            '<div class="aiquizmaker-alert-content">' +
            '<p class="aiquizmaker-alert-title">' + title + '</p>' +
            '<p class="aiquizmaker-alert-message">' + message + '</p>' +
            '</div></div>';

        $('.aiquizmaker-header').after(alertHtml);

        setTimeout(function() {
            $('.aiquizmaker-alert').first().fadeOut(300, function() {
                $(this).remove();
            });
        }, 8000);
    }

    /**
     * Update the credits display.
     * @param {number} credits The current credit balance.
     */
    function updateCredits(credits) {
        var creditsEl = $('#aiquizmaker-credits-count');
        var badge = $('#aiquizmaker-credits-badge');

        creditsEl.text(credits);

        badge.removeClass('aiquizmaker-credits-low aiquizmaker-credits-empty');
        if (credits === 0) {
            badge.addClass('aiquizmaker-credits-empty');
        } else if (credits < 10) {
            badge.addClass('aiquizmaker-credits-low');
        }
    }

    /**
     * Fetch the current credit balance from the server.
     */
    function fetchCredits() {
        log('Fetching credits...');

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'getcredits',
                sesskey: config.sesskey
            },
            dataType: 'json'
        }).done(function(response) {
            log('Credits response:', response);
            if (response.success) {
                updateCredits(response.credits);
            } else {
                $('#aiquizmaker-credits-badge').hide();
            }
        }).fail(function(xhr, status, error) {
            log('Credits request failed:', error);
            $('#aiquizmaker-credits-badge').hide();
        });
    }

    /**
     * Update the state/region dropdown based on selected country.
     * @param {string} country The selected country.
     */
    function updateStateDropdown(country) {
        log('Updating states for country:', country);
        var select = $('#state-select');
        var states = countryStates[country] || [];

        select.empty();
        select.append('<option value="">' + (strings.select_state || 'Select state...') + '</option>');

        if (states.length > 0) {
            states.forEach(function(state) {
                select.append('<option value="' + state.value + '">' + state.label + '</option>');
            });
            select.closest('.aiquizmaker-field').show();
        } else {
            // Hide the state field for countries without states (e.g., Singapore)
            select.closest('.aiquizmaker-field').show();
        }
    }

    /**
     * Escape HTML special characters.
     * @param {string} str The string to escape.
     * @return {string} Escaped string.
     */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Update the job level checkbox group based on selected industry.
     * Re-renders checkbox pills inside #job-level-checkboxes.
     * @param {string} industry The selected industry.
     */
    function updateJobLevelCheckboxes(industry) {
        log('Updating job level checkboxes for industry:', industry);
        var container = $('#job-level-checkboxes');
        var levels = defaultJobLevels;

        if (industryJobMatrix[industry]) {
            levels = Object.keys(industryJobMatrix[industry]);
        }

        // Preserve currently checked values so user selections survive re-render.
        var checked = {};
        container.find('input[name="job_level[]"]').each(function() {
            if ($(this).is(':checked')) {
                checked[$(this).val()] = true;
            }
        });

        container.empty();
        levels.forEach(function(level) {
            var wasChecked = checked[level] ? ' checked="checked"' : '';
            var item = '<div class="aiquizmaker-checkbox-item">' +
                '<label class="aiquizmaker-checkbox-label">' +
                '<input type="checkbox" name="job_level[]" value="' + escapeHtml(level) +
                '" class="aiquizmaker-checkbox"' + wasChecked + '>' +
                '<span class="aiquizmaker-checkbox-text">' + escapeHtml(level) + '</span>' +
                '</label></div>';
            container.append(item);
        });
    }

    /**
     * Update the job title checkbox panel based on selected industry and job levels.
     * Shows/hides the panel and populates #job-title-checkboxes.
     * @param {string} industry The selected industry.
     * @param {Array} jobLevels Array of selected job level strings (may be empty).
     */
    function updateJobTitlePanel(industry, jobLevels) {
        log('Updating job title panel for:', industry, jobLevels);
        var panel = $('#job-title-panel');
        var list = $('#job-title-checkboxes');
        var titles = [];

        if (industry && industryJobMatrix[industry]) {
            if (jobLevels && jobLevels.length > 0) {
                // Aggregate titles from all selected levels (deduplicated).
                jobLevels.forEach(function(level) {
                    if (industryJobMatrix[industry][level]) {
                        industryJobMatrix[industry][level].forEach(function(title) {
                            if (titles.indexOf(title) === -1) {
                                titles.push(title);
                            }
                        });
                    }
                });
            } else {
                // No levels selected  -  show ALL titles for this industry.
                Object.keys(industryJobMatrix[industry]).forEach(function(level) {
                    industryJobMatrix[industry][level].forEach(function(title) {
                        if (titles.indexOf(title) === -1) {
                            titles.push(title);
                        }
                    });
                });
            }
        }

        titles.sort();

        // Preserve previously checked values across re-renders.
        var checked = {};
        list.find('input[name="job_title[]"]').each(function() {
            if ($(this).is(':checked')) {
                checked[$(this).val()] = true;
            }
        });

        list.empty();

        if (titles.length > 0) {
            titles.forEach(function(title) {
                var wasChecked = checked[title] ? ' checked="checked"' : '';
                var item = '<div class="aiquizmaker-checkbox-item aiquizmaker-role-item" data-title="' + escapeHtml(title.toLowerCase()) + '">' +
                    '<label class="aiquizmaker-checkbox-label">' +
                    '<input type="checkbox" name="job_title[]" value="' + escapeHtml(title) +
                    '" class="aiquizmaker-checkbox"' + wasChecked + '>' +
                    '<span class="aiquizmaker-checkbox-text">' + escapeHtml(title) + '</span>' +
                    '</label></div>';
                list.append(item);
            });
            panel.show();
        } else {
            panel.hide();
        }

        // Re-apply any active search filter.
        var searchVal = $('#job-title-search').val();
        if (searchVal) {
            filterJobTitles(searchVal);
        }
    }

    /**
     * Filter visible job title checkboxes by search text.
     * @param {string} query The search string.
     */
    function filterJobTitles(query) {
        var q = query.toLowerCase().trim();
        $('#job-title-checkboxes .aiquizmaker-role-item').each(function() {
            var title = $(this).data('title') || '';
            if (!q || title.indexOf(q) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    /**
     * Populate industries dropdown with fallback list.
     * @param {Array} industries List of industries.
     */
    function populateIndustries(industries) {
        var select = $('#industry-select');
        select.empty();
        select.append('<option value="">' + (strings.select_industry || 'Select industry...') + '</option>');
        industries.forEach(function(industry) {
            select.append('<option value="' + industry + '">' + industry + '</option>');
        });
    }

    /**
     * Fetch available question categories from Moodle.
     */
    function fetchCategories() {
        log('Fetching question categories...');

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'getcategories',
                sesskey: config.sesskey
            },
            dataType: 'json'
        }).done(function(response) {
            log('Categories response:', response);
            if (response.success && response.categories) {
                categories = response.categories;
                log('Loaded ' + categories.length + ' categories');
            }
        }).fail(function(xhr, status, error) {
            log('Categories request failed:', error);
        });
    }

    /**
     * Build category dropdown HTML.
     * @return {string} HTML for category select element.
     */
    function buildCategoryDropdown() {
        if (categories.length === 0) {
            return '<p class="em-text-muted">No question categories available. Please create a course category first.</p>';
        }

        var html = '<select id="question-category-select" class="aiquizmaker-select">';
        html += '<option value="">Select question category...</option>';

        // Group by course
        var grouped = {};
        categories.forEach(function(cat) {
            if (!grouped[cat.course]) {
                grouped[cat.course] = [];
            }
            grouped[cat.course].push(cat);
        });

        Object.keys(grouped).forEach(function(course) {
            html += '<optgroup label="' + escapeHtml(course) + '">';
            grouped[course].forEach(function(cat) {
                html += '<option value="' + cat.id + '">' + escapeHtml(cat.name) + '</option>';
            });
            html += '</optgroup>';
        });

        html += '</select>';
        return html;
    }

    /**
     * Create questions directly in Moodle question bank.
     * @param {number} categoryId The category ID to create questions in.
     */
    function createQuestionsInMoodle(categoryId) {
        if (!categoryId) {
            showAlert('error', 'Please select a category', 'Choose a question category from the dropdown.');
            return;
        }

        if (lastGeneratedQuestions.length === 0) {
            showAlert('error', 'No questions to create', 'Please generate questions first.');
            return;
        }

        // Fix 1: Only create teacher-selected questions.
        var selectedQuestions = getSelectedQuestions();
        if (selectedQuestions.length === 0) {
            showAlert('warning', 'No questions selected', 'Please tick at least one question using the checkboxes before creating.');
            return;
        }

        log('Creating ' + selectedQuestions.length + ' selected questions in category ' + categoryId);
        showLoading(true, 'Creating questions in Moodle...');

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'createquestions',
                sesskey: config.sesskey,
                categoryid: categoryId,
                questions: JSON.stringify(selectedQuestions)
            },
            dataType: 'json',
            timeout: 60000
        }).done(function(response) {
            log('Create questions response:', response);
            showLoading(false);

            if (response.success) {
                // F3: Include skip/error detail in success message so teacher knows if some questions were skipped.
                var skipDetail = '';
                if (response.skipped && response.skipped.length > 0) {
                    skipDetail = ' (' + response.skipped.length + ' skipped: ' + response.skipped.join('; ') + ')';
                }
                if (response.errors && response.errors.length > 0) {
                    skipDetail += ' Errors: ' + response.errors.join('; ');
                }
                showAlert('success', 'Questions created!',
                    response.created + ' question(s) added to your Moodle question bank. You can now add them to any quiz.' + skipDetail);
                $('#create-questions-section').html(
                    '<div class="em-alert em-alert-success">' +
                    '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                    '<span>' + response.created + ' question(s) created in Moodle!' + (skipDetail ? ' ' + skipDetail : '') + '</span>' +
                    '</div>'
                );
            } else {
                var errMsg = response.error || 'Could not create questions.';
                if (response.skipped && response.skipped.length > 0) {
                    errMsg += ' Skipped: ' + response.skipped.join('; ');
                }
                showAlert('error', 'Creation failed', errMsg);
                if (response.errors && response.errors.length > 0) {
                    log('Errors:', response.errors);
                }
            }
        }).fail(function(xhr, status, error) {
            log('Create questions failed:', error);
            showLoading(false);
            showAlert('error', 'Connection error', 'Could not connect to Moodle. Please try again.');
        });
    }

    /**
     * Download XML as a file.
     */
    function downloadXml() {
        var xmlContent = $('#moodle-xml').val();
        if (!xmlContent) {
            showAlert('error', 'No XML', 'Generate questions first.');
            return;
        }

        var blob = new Blob([xmlContent], {type: 'application/xml'});
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'essay_questions.xml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    /**
     * Download Excel/CSV mapping file showing question-to-criteria mapping.
     */
    function downloadExcel() {
        if (!lastGeneratedQuestions || lastGeneratedQuestions.length === 0) {
            showAlert('error', 'No Questions', 'Generate questions first.');
            return;
        }

        // Build CSV content with BOM for Excel UTF-8 compatibility
        var bom = '\uFEFF';
        var csvRows = [];

        // Header row - Criteria first (left column) for mapping clarity
        csvRows.push(['Criteria', 'Question Number', 'Question Text', 'Total Marks'].join(','));

        // Data rows - Criteria in first column
        lastGeneratedQuestions.forEach(function(q, index) {
            var questionNumber = index + 1;
            // Escape quotes and wrap in quotes for CSV
            var criteria = '"' + (q.criteriaReference || '').replace(/"/g, '""') + '"';
            var questionText = '"' + (q.questionText || '').replace(/"/g, '""') + '"';
            var totalMarks = q.totalMarks || 3;

            csvRows.push([criteria, questionNumber, questionText, totalMarks].join(','));
        });

        var csvContent = bom + csvRows.join('\n');
        var blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'question_criteria_mapping.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    /**
     * Fetch available industries from the server.
     */
    function fetchIndustries() {
        log('Fetching industries...');

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'getindustries',
                sesskey: config.sesskey
            },
            dataType: 'json'
        }).done(function(response) {
            log('Industries response:', response);
            // Check for both success and ok response formats, or just industries array
            if ((response.success || response.ok) && response.industries) {
                populateIndustries(response.industries);
            } else if (response.industries) {
                // Direct industries array without success flag
                populateIndustries(response.industries);
            } else {
                // Use fallback industries
                log('Using fallback industries');
                populateIndustries(fallbackIndustries);
            }
        }).fail(function(xhr, status, error) {
            log('Industries request failed, using fallback:', error);
            populateIndustries(fallbackIndustries);
        });
    }

    /**
     * Add a new criteria row to the form.
     */
    function addCriteriaRow() {
        criteriaIndex++;
        var html = '<div class="aiquizmaker-criteria-row" data-index="' + criteriaIndex + '">' +
            '<div class="aiquizmaker-criteria-inputs">' +
            '<input type="text" name="criteria[' + criteriaIndex + '][text]" class="aiquizmaker-input" placeholder="' + strings.criteria_placeholder + '">' +
            '<select name="criteria[' + criteriaIndex + '][count]" class="aiquizmaker-select">';

        for (var i = 1; i <= 10; i++) {
            var questionText = i === 1 ? strings.question_singular : strings.question_plural;
            // Replace {$a} placeholder with the number
            var label = questionText.replace('{$a}', i);
            html += '<option value="' + i + '">' + label + '</option>';
        }

        html += '</select></div>' +
            '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-criteria">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button></div>';

        $('#criteria-container').append(html);
        updateRemoveButtons();
    }

    /**
     * Remove a criteria row from the form.
     * @param {jQuery} row The row to remove.
     */
    function removeCriteriaRow(row) {
        row.remove();
        updateRemoveButtons();
    }

    /**
     * Update visibility of remove buttons.
     */
    function updateRemoveButtons() {
        var rows = $('.aiquizmaker-criteria-row');
        rows.each(function() {
            var btn = $(this).find('.aiquizmaker-remove-criteria');
            if (rows.length <= 1) {
                btn.css('visibility', 'hidden');
            } else {
                btn.css('visibility', 'visible');
            }
        });
    }

    /**
     * Add a criteria row with pre-filled text.
     * @param {string} text The criteria text to pre-fill.
     */
    function addCriteriaRowWithText(text) {
        criteriaIndex++;
        var html = '<div class="aiquizmaker-criteria-row" data-index="' + criteriaIndex + '">' +
            '<div class="aiquizmaker-criteria-inputs">' +
            '<input type="text" name="criteria[' + criteriaIndex + '][text]" class="aiquizmaker-input" placeholder="' + strings.criteria_placeholder + '" value="' + escapeHtml(text) + '">' +
            '<select name="criteria[' + criteriaIndex + '][count]" class="aiquizmaker-select">';

        for (var i = 1; i <= 10; i++) {
            var questionText = i === 1 ? strings.question_singular : strings.question_plural;
            var label = questionText.replace('{$a}', i);
            html += '<option value="' + i + '">' + label + '</option>';
        }

        html += '</select></div>' +
            '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-criteria">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button></div>';

        $('#criteria-container').append(html);
        updateRemoveButtons();
    }

    /**
     * Escape HTML special characters.
     * @param {string} text The text to escape.
     * @return {string} The escaped text.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Clean up text by removing extra blank lines and whitespace.
     * @param {string} text The text to clean.
     * @return {string} Cleaned text.
     */
    function cleanupPastedText(text) {
        return text
            // Normalize line endings
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            // Remove multiple consecutive blank lines (keep single line breaks)
            .replace(/\n{3,}/g, '\n\n')
            // Trim whitespace from each line
            .split('\n')
            .map(function(line) { return line.trim(); })
            .join('\n')
            // Trim the whole string
            .trim();
    }

    /**
     * Bulk add criteria from textarea.
     */
    function bulkAddCriteria() {
        var textarea = $('#bulk-criteria-input');
        var text = cleanupPastedText(textarea.val());

        if (!text) {
            showAlert('warning', strings.bulk_add_empty || 'No criteria to add', '');
            return;
        }

        // Split by newlines and filter empty lines
        var lines = text.split('\n')
            .map(function(line) { return line.trim(); })
            .filter(function(line) { return line.length > 0; });

        if (lines.length === 0) {
            showAlert('warning', strings.bulk_add_empty || 'No criteria to add', '');
            return;
        }

        // Check if first criteria row is empty, if so fill it instead of adding new
        var firstRow = $('.aiquizmaker-criteria-row').first();
        var firstInput = firstRow.find('input[name*="[text]"]');
        var startIndex = 0;
        var addedCount = lines.length;

        if (firstInput.val().trim() === '') {
            firstInput.val(lines[0]);
            startIndex = 1;
            // Don't count the first one as "added" since we just filled an existing empty row
            if (lines.length === 1) {
                addedCount = 1; // Still count as 1 since we populated it
            }
        }

        // Add remaining criteria as new rows
        for (var i = startIndex; i < lines.length; i++) {
            addCriteriaRowWithText(lines[i]);
        }

        // Clear the textarea
        textarea.val('');

        // Show success message
        var successMsg = (strings.bulk_add_success || '{$a} criteria added').replace('{$a}', addedCount);
        showAlert('success', successMsg, '');

        log('Bulk added ' + addedCount + ' criteria');
    }

    /**
     * Extract assessable criteria from pasted content using AI, then populate criteria rows.
     */
    function extractCriteriaFromContent() {
        var content = $('#pasted-content-input').val() || '';
        if (!content.trim()) {
            showAlert('warning', 'No content', 'Please paste some learning content first.');
            return;
        }

        var btn = $('#extract-criteria-btn');
        var labelEl = btn.find('.extract-criteria-btn-label');
        var originalLabel = labelEl.text();
        btn.prop('disabled', true).addClass('is-loading');
        labelEl.text(strings.extract_criteria_loading || 'Extracting...');

        var educationType = $('select[name="education_type"]').val() || 'vet';

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'extractcriteria',
                sesskey: config.sesskey,
                pastedContent: content,
                educationType: educationType
            },
            dataType: 'json',
            timeout: 60000
        }).done(function(response) {
            btn.prop('disabled', false).removeClass('is-loading');
            labelEl.text(originalLabel);

            if (!response.success || !response.criteria || response.criteria.length === 0) {
                var errMsg = response.error || strings.extract_criteria_none || 'No criteria found in the pasted content.';
                showAlert('warning', 'No Criteria Found', errMsg);
                return;
            }

            var criteria = response.criteria;
            var addedCount = criteria.length;

            // Check if first criteria row is empty  -  fill it instead of adding new
            var firstRow = $('.aiquizmaker-criteria-row').first();
            var firstInput = firstRow.find('input[name*="[text]"]');
            var startIndex = 0;

            if (firstInput.val().trim() === '') {
                firstInput.val(criteria[0]);
                startIndex = 1;
            }

            for (var i = startIndex; i < criteria.length; i++) {
                addCriteriaRowWithText(criteria[i]);
            }

            var successMsg = (strings.bulk_add_success || '{$a} criteria added').replace('{$a}', addedCount);
            showAlert('success', successMsg + ' from content', '');
            log('Extracted ' + addedCount + ' criteria from pasted content');

        }).fail(function() {
            btn.prop('disabled', false).removeClass('is-loading');
            labelEl.text(originalLabel);
            showAlert('error', 'Error', strings.extract_criteria_error || 'Failed to extract criteria. Please try again.');
        });
    }

    /**
     * Apply selected question count to all criteria rows.
     */
    function applyCountToAll() {
        var selectedCount = $('#apply-all-count').val();
        var criteriaRows = $('.aiquizmaker-criteria-row');
        var updatedCount = 0;

        criteriaRows.each(function() {
            var select = $(this).find('select[name*="[count]"]');
            if (select.length) {
                select.val(selectedCount);
                updatedCount++;
            }
        });

        if (updatedCount > 0) {
            var successMsg = (strings.apply_to_all_success || 'Question count applied to all {$a} criteria').replace('{$a}', updatedCount);
            showAlert('success', successMsg, '');
            log('Applied count ' + selectedCount + ' to ' + updatedCount + ' criteria');
        }
    }

    /**
     * Get the selected job titles  -  all checked checkboxes plus any custom text input.
     * @return {string} Comma-separated job titles.
     */
    function getJobTitle() {
        var selected = [];
        $('input[name="job_title[]"]:checked').each(function() {
            selected.push($(this).val());
        });
        var customVal = $.trim($('#job-title-custom').val());
        if (customVal) {
            customVal.split(',').forEach(function(part) {
                var t = $.trim(part);
                if (t && selected.indexOf(t) === -1) {
                    selected.push(t);
                }
            });
        }
        return selected.join(', ');
    }

    /**
     * Get the selected job levels as an array.
     * @return {Array} Array of selected job level strings.
     */
    function getSelectedJobLevels() {
        return $('input[name="job_level[]"]:checked').map(function() {
            return $(this).val();
        }).get();
    }

    /**
     * Get selected question formats.
     * @return {Array} Array of selected format values.
     */
    function getSelectedQuestionFormats() {
        var formats = [];
        $('input[name="question_formats[]"]:checked').each(function() {
            formats.push($(this).val());
        });
        return formats;
    }

    /**
     * Get education level based on education type.
     * @return {string} The education level.
     */
    function getEducationLevel() {
        var educationType = $('select[name="education_type"]').val();
        if (educationType === 'academic') {
            return $('select[name="academic_level"]').val() || '';
        } else {
            return $('select[name="vet_level"]').val() || '';
        }
    }

    /**
     * Check if workplace context is enabled.
     * @return {boolean} True if workplace context toggle is checked.
     */
    function isWorkplaceContextEnabled() {
        return $('#workplace-context-toggle').is(':checked');
    }

    /**
     * Get selected Moodle question types from checkboxes.
     * @return {Array} Array of selected question type strings.
     */
    function getMoodleQuestionTypes() {
        var types = [];
        $('.aiquizmaker-qtype-check:checked').each(function() {
            types.push($(this).val());
        });
        if (types.length === 0) {
            types = ['essay'];
        }
        return types;
    }

    /**
     * Get selected self-marking question styles from checkboxes.
     * @return {Array} Array of selected style strings.
     */
    function getSelectedSelfMarkingStyles() {
        var styles = [];
        $('input[name="selfmarking_styles[]"]:checked').each(function() {
            styles.push($(this).val());
        });
        if (styles.length === 0) {
            styles = ['scenario', 'knowledge_check'];
        }
        return styles;
    }

    /**
     * Show/hide format sections based on selected question types.
     * - Essay formats section shown when essay type is checked.
     * - Self-marking styles section shown when any non-essay type is checked.
     */
    function updateFormatSectionsVisibility() {
        var selectedTypes = getMoodleQuestionTypes();
        var hasEssay = selectedTypes.indexOf('essay') !== -1;
        var hasNonEssay = selectedTypes.some(function(t) { return t !== 'essay'; });

        var $essayFormats = $('.aiquizmaker-essay-formats');
        var $selfMarkingStyles = $('.aiquizmaker-selfmarking-styles');

        if ($essayFormats.length) {
            $essayFormats.toggle(hasEssay);
        }
        if ($selfMarkingStyles.length) {
            $selfMarkingStyles.toggle(hasNonEssay);
        }
    }

    /**
     * Collect form data from the generation form.
     * @return {Object} Form data object.
     */
    function collectFormData() {
        var criteria = [];
        $('.aiquizmaker-criteria-row').each(function() {
            var text = $(this).find('input[name*="[text]"]').val();
            var count = $(this).find('select[name*="[count]"]').val();
            if (text) {
                // Fix 4: Strip MCQ option blocks from criteria text before sending to AI.
                // Strips "Option A: ...", "Option B: ..." etc. from the end of criteria lines.
                text = text.replace(/\s*\bOption\s+[A-D]\b.*$/i, '').trim();
                // Also strip standalone letter options like " A. Something B. Something"
                text = text.replace(/\s+[A-D]\.\s+\S.*$/i, '').trim();
                // Cap criteria at 300 chars to keep prompts focused
                if (text.length > 300) { text = text.substring(0, 300); }
                criteria.push({
                    text: text,
                    count: parseInt(count) || 1
                });
            }
        });

        // Collect pasted learning content (optional source material)
        var pastedContent = $('#pasted-content-input').val() || '';

        var educationType = $('select[name="education_type"]').val();
        var workplaceContextEnabled = isWorkplaceContextEnabled();

        var formData = {
            criteria: criteria,
            pastedContent: pastedContent,
            workplaceContextEnabled: workplaceContextEnabled,
            educationType: educationType,
            educationLevel: getEducationLevel(),
            questionFormats: getSelectedQuestionFormats(),
            moodleQuestionTypes: getMoodleQuestionTypes()
        };

        // Only include workplace context fields if enabled
        if (workplaceContextEnabled) {
            formData.country = $('select[name="country"]').val();
            formData.state = $('select[name="state"]').val();
            formData.industry = $('select[name="industry"]').val();
            formData.industryDetails = $('input[name="industry_details"]').val();
            formData.jobTitle = getJobTitle();
            formData.jobLevel = getSelectedJobLevels().join(', ');
        } else {
            // Empty values when disabled
            formData.country = '';
            formData.state = '';
            formData.industry = '';
            formData.industryDetails = '';
            formData.jobTitle = '';
            formData.jobLevel = '';
        }

        return formData;
    }

    /**
     * Generate essay questions based on form data.
     * All inputs are optional - the AI will generate generic questions if no context provided.
     */
    function generateQuestions() {
        var data = collectFormData();

        // Fix 4: Filter criteria shorter than 10 chars (weak/empty entries produce bad AI output)
        data.criteria = data.criteria.filter(function(c) {
            return c.text && c.text.trim().length >= 10;
        });

        // If no criteria provided, use a default generic one
        if (data.criteria.length === 0) {
            data.criteria = [{
                text: 'General knowledge questions',
                count: 3
            }];
        }

        // All other fields are now optional - no validation required
        // The AI will generate appropriate questions based on available context

        var totalQuestions = data.criteria.reduce(function(sum, c) {
            return sum + c.count;
        }, 0);

        log('Generating ' + totalQuestions + ' questions...');
        showLoading(true, strings.generating + ' ' + totalQuestions + '...');

        var btn = $('#generate-btn');
        btn.prop('disabled', true);

        // Get extra instructions if available
        var extraInstructions = $('#aiquizmaker-extra-instructions').val() || '';

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'generate',
                sesskey: config.sesskey,
                cmid: config.cmid || 0,
                criteria: JSON.stringify(data.criteria),
                workplaceContextEnabled: data.workplaceContextEnabled ? '1' : '0',
                country: data.country,
                state: data.state,
                industry: data.industry,
                industryDetails: data.industryDetails,
                jobTitle: data.jobTitle,
                jobLevel: data.jobLevel,
                educationType: data.educationType,
                educationLevel: data.educationLevel,
                questionFormats: JSON.stringify(data.questionFormats),
                moodleQuestionTypes: JSON.stringify(data.moodleQuestionTypes),
                selfMarkingStyles: JSON.stringify(getSelectedSelfMarkingStyles()),
                extraInstructions: extraInstructions,
                pastedContent: data.pastedContent || '',
                language: config.language || ''
            },
            dataType: 'json',
            timeout: 180000
        }).done(function(response) {
            log('Generate response:', response);
            showLoading(false);
            btn.prop('disabled', false);

            if (response.success) {
                displayResults(response.questions, response.moodleXml, response.addedToQuiz, response.quizName, response.hasQuizContext);
                updateCredits(response.credits);
                showAlert('success', strings.success_generated, strings.success_generated_message.replace('{$a}', response.questionsGenerated));
            } else {
                if (response.error === 'INSUFFICIENT_CREDITS') {
                    var buyUrl = response.buyUrl || config.buyurl;
                    showAlert('warning', strings.insufficient_credits,
                        strings.insufficient_credits_message + ' <a href="' + buyUrl + '" target="_blank">' + strings.buy_credits + '</a>');
                } else {
                    var errMsg = response.message || response.error || strings.error_generation;
                    showAlert('error', strings.error_generation, errMsg);
                }
            }
        }).fail(function(xhr, status, error) {
            log('Generate request failed - HTTP', xhr.status, '-', error, '-', xhr.responseText);
            showLoading(false);
            btn.prop('disabled', false);
            var failMsg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : (xhr.status ? 'HTTP ' + xhr.status + ': ' + (error || 'Unknown error') : strings.error_connection);
            showAlert('error', strings.error_connection, failMsg);
        });
    }

    /**
     * Detect whether a line is a section heading (Part A, Section 1, etc.)
     * rather than an actual question to be sent to the AI.
     * @param {string} line Trimmed line from the textarea.
     * @return {boolean}
     */
    function isHeadingLine(line) {
        if (!line || line.length < 2 || line.length > 150) return false;
        // Real questions almost always end with a '?' or are long
        if (line.endsWith('?') && line.length > 20) return false;
        // Part A, Part B, Part 1, Part II, Section A, Section 1, Chapter 3 etc.
        if (/^(part|section|chapter|module|unit|task|activity|topic)\s+[a-z0-9ivxlcdm]+/i.test(line)) return true;
        // Short line ending with a colon and no question mark  ->  heading.
        // Guard against imperative question starters (Describe:, List:, Explain: etc.).
        var questionVerb = /^(list|describe|explain|identify|discuss|outline|compare|analyse|analyze|evaluate|define|state|name|calculate|demonstrate|assess|review|examine|complete|provide|write|select|choose|answer)\b/i;
        if (line.endsWith(':') && line.length <= 80 && !line.includes('?') && !questionVerb.test(line)) return true;
        // All-caps short line with at least 2 alpha chars (e.g. "SECTION A", "INTRODUCTION").
        // Exclude standalone generic words that are not meaningful section headings  -  these
        // would create empty-looking description cards and cause confusion in the quiz editor.
        var standaloneBlocklist = /^(INFORMATION|NOTE|NOTES|IMPORTANT|WARNING|INSTRUCTIONS|INSTRUCTION|OVERVIEW|INTRODUCTION|CONCLUSION|SUMMARY|DESCRIPTION|DETAILS|GENERAL|ADDITIONAL|EXTRA|OTHER|CONTEXT|BACKGROUND)$/;
        if (line.length <= 60 && line === line.toUpperCase() && /[A-Z]{2,}/.test(line) && !/[0-9]{4,}/.test(line) && !standaloneBlocklist.test(line.trim())) return true;
        return false;
    }

    /**
     * Switch the input mode between 'criteria' and 'ownquestions'.
     * Shows/hides relevant sections and updates the generate button label.
     * @param {string} mode 'criteria' or 'ownquestions'
     */
    function switchInputMode(mode) {
        currentInputMode = mode;

        // Update tab pill active states.
        $('.aiquizmaker-mode-tab').removeClass('aiquizmaker-mode-tab-active');
        $('#mode-tab-' + mode).addClass('aiquizmaker-mode-tab-active');

        // v3.16.40: Clear previous questions/results on every mode switch so that
        // questions generated in one mode never bleed into another mode's view.
        // v3.16.42: Use targeted clear instead of .empty() on the whole container  - 
        // .empty() destroyed the static #questions-list element, causing all subsequent
        // displayResults() calls to append to a detached jQuery set (no-op), so zero
        // question cards were rendered after any mode switch.
        lastGeneratedQuestions = [];
        $('#questions-list').empty();
        $('#aiquizmaker-results').hide();
        $('#aiquizmaker-xml-section').hide().find('#moodle-xml').val('');
        $('.aiquizmaker-alert').remove();

        if (mode === 'ownquestions') {
            $('#aiquizmaker-content-section').hide();
            $('#aiquizmaker-criteria-section').hide();
            $('#own-questions-section').show();
            $('#generate-btn-label').text(strings.ownquestions_btn || 'Generate for My Questions');
        } else {
            $('#aiquizmaker-content-section').show();
            $('#aiquizmaker-criteria-section').show();
            $('#own-questions-section').hide();
            $('#generate-btn-label').text(strings.generate_btn || 'Generate Questions');
        }

        log('Input mode switched to: ' + mode);
    }

    /**
     * Build a comprehensive ChatGPT prompt that produces output in the exact format this plugin expects.
     *
     * The plugin supports two output structures from ChatGPT:
     *  1. Simple:  Q1. question\n\nQ2. question  (one per block, blank line between)
     *  2. Block:   Q1. question\nModel Response:\nanswer\n\n[Part X: heading]\n\nQ2. ...
     *
     * Section headings (Part A:, Part B:, etc.) become Moodle description cards  -  they are not
     * graded questions; they render as formatted separator cards between question groups in the quiz.
     *
     * @param {string}  topic          Topic or subject area entered by the teacher
     * @param {number}  count          Total number of questions to generate
     * @param {string}  level          Education level string (empty string = not specified)
     * @param {boolean} modelResponses Whether to include Model Response blocks
     * @param {boolean} headings       Whether to include section heading (description card) examples
     * @return {string} The ready-to-paste ChatGPT prompt
     */
    function buildChatGPTPrompt(topic, count, level, modelResponses, headings, types) {
        var levelLine = level ? 'Questions should be written at ' + level + ' level.' : '';
        var sectionCount = headings ? Math.min(3, Math.ceil(count / 3)) : 0;
        // Normalise types: default to essay only
        var qtypes = (types && types.length > 0) ? types : ['essay'];
        var nonEssayTypes = qtypes.filter(function(t) { return t !== 'essay'; });
        var hasNonEssay = nonEssayTypes.length > 0;
        var typeLabels = {
            'essay': 'Written Essay',
            'multichoice': 'Multiple Choice (4 options)',
            'truefalse': 'True/False',
            'matching': 'Matching (4 pairs)',
            'shortanswer': 'Short Answer / Missing Word'
        };
        var selectedTypeNames = qtypes.map(function(t) { return typeLabels[t] || t; });

        // -- Example format block ----------------------------------------------
        var ex = [];

        if (headings) {
            ex.push('Part A: Legislative Framework and Employer Obligations');
            ex.push('');
        }

        if (modelResponses) {
            ex.push('Q1. Describe the key legal obligations an employer has under the Work Health and Safety Act 2011.');
            ex.push('Model Response:');
            ex.push('Employers have a primary duty of care under the Work Health and Safety Act 2011 to ensure, so far as is reasonably practicable, the health, safety and welfare of all workers. This includes providing and maintaining safe systems of work, safe premises, adequate supervision and training, and meaningful consultation with workers on health and safety matters. Failure to comply may result in significant financial penalties and prosecution.');
            ex.push('');
            if (headings && sectionCount >= 2) {
                ex.push('Part B: Hazard Identification and Risk Management');
                ex.push('');
            }
            ex.push('Q2. Explain the difference between a hazard and a risk, and provide one example of each from a workplace setting.');
            ex.push('Model Response:');
            ex.push('A hazard is any source or situation with the potential to cause harm, injury, or illness  -  for example, a wet floor in a commercial kitchen. A risk is the likelihood that the hazard will actually result in harm combined with the severity of that potential harm. In this example, the risk is a worker slipping and sustaining a serious injury. Distinguishing between hazards and risks is the foundation of any effective risk management process.');
            ex.push('');
            if (headings && sectionCount >= 3) {
                ex.push('Part C: Incident Reporting and Documentation');
                ex.push('');
            }
            ex.push('Q3. Outline the steps a worker must follow when reporting a workplace incident or near miss.');
            ex.push('Model Response:');
            ex.push('When a workplace incident or near miss occurs, the worker must immediately notify their direct supervisor or manager. The incident must then be recorded using the organisation\'s hazard or incident report form, capturing details such as date, time, location, people involved, and a description of what occurred. The completed report is submitted to the WHS officer or manager for investigation and corrective action. All incidents must be retained in the organisation\'s records management system in accordance with legislative requirements.');
        } else {
            ex.push('Q1. Describe the key legal obligations an employer has under the Work Health and Safety Act 2011.');
            ex.push('');
            if (headings && sectionCount >= 2) {
                ex.push('Part B: Hazard Identification and Risk Management');
                ex.push('');
            }
            ex.push('Q2. Explain the difference between a hazard and a risk, and provide one workplace example of each.');
            ex.push('');
            if (headings && sectionCount >= 3) {
                ex.push('Part C: Incident Reporting and Documentation');
                ex.push('');
            }
            ex.push('Q3. Outline the steps a worker must follow when reporting a workplace incident or near miss.');
        }

        var exampleBlock = ex.join('\n');

        // -- Rules section -----------------------------------------------------
        var rules = [];

        rules.push('QUESTION NUMBERING:');
        rules.push('  * Number every question Q1., Q2., Q3. consecutively throughout the ENTIRE output.');
        rules.push('  * Do NOT restart numbering after each section heading (e.g. if Part A has Q1 - Q4, Part B starts at Q5).');
        rules.push('  * The numbering must be continuous from the first question to the last.');
        rules.push('');

        if (headings) {
            rules.push('DESCRIPTION CARDS  -  SECTION HEADINGS  -  CRITICAL:');
            rules.push('  * Moodle description cards are non-question items that appear as formatted heading cards between groups of questions in the quiz view. They are not graded and cost no credits.');
            rules.push('  * To create a description card, output a heading line that:');
            rules.push('      (a) is SHORT  -  under 80 characters');
            rules.push('      (b) ends with a COLON  -  e.g. "Part A: Legislative Framework and Employer Obligations"');
            rules.push('      (c) is on its OWN LINE with a blank line before it AND a blank line after it');
            rules.push('      (d) contains NO question text');
            rules.push('  * Valid heading formats: "Part A: Topic Name", "Part B: Topic Name", "Assessment Task 1:", "Section 1: Topic Name"');
            rules.push('  * Invalid (will NOT be detected as a heading): a heading with a full stop after the colon, a heading over 80 chars, a heading that shares a line with question text.');
            rules.push('  * Distribute the ' + count + ' questions roughly evenly across ' + sectionCount + ' sections.');
            rules.push('  * Do NOT add a heading after the very last question  -  headings only appear before groups of questions.');
            rules.push('');
        }

        if (modelResponses) {
            rules.push('MODEL RESPONSE  -  CRITICAL:');
            rules.push('  * After every question, write the word "Model Response:" on its own line  -  exactly that text, with the colon, nothing else on that line.');
            rules.push('  * Start the model answer IMMEDIATELY on the next line (no blank line between "Model Response:" and the answer).');
            rules.push('  * The model answer must be a COMPLETE, DETAILED written response that a competent student would produce.');
            rules.push('  * Minimum 3 full sentences. Cover all key points the marking rubric should assess.');
            rules.push('  * Name specific legislation, standards, codes of practice, or frameworks where relevant (e.g. WHS Act 2011, NDIS Practice Standards, National Quality Standard, Food Standards Code).');
            rules.push('  * Do NOT write vague answers like "Students should describe the process." Write the actual answer.');
            rules.push('  * The model answer is used by the AI to generate the marking rubric  -  every key point you include will become a rubric criterion, so be thorough and specific.');
            rules.push('  * Do NOT add a blank line inside a question block  -  question text  ->  "Model Response:"  ->  answer must appear as three consecutive lines with no gaps.');
            rules.push('');
        }

        rules.push('QUESTION QUALITY:');
        if (hasNonEssay) {
            rules.push('  * Write clear, concise question text appropriate for the topic. The plugin will generate answer choices, pairs, or answer options automatically.');
            rules.push('  * Use clear question language: "What is...", "Which of the following...", "Identify...", "Define...", "Explain..." etc.');
        } else {
            rules.push('  * Every question must require a WRITTEN RESPONSE  -  full sentences, structured argument, or practical explanation.');
            rules.push('  * Questions should use verbs such as: Describe, Explain, Outline, Discuss, Identify and explain, Compare and contrast, Analyse, Evaluate.');
        }
        rules.push('  * Each question must be independently answerable without reference to other questions.');
        rules.push('  * Vary the cognitive demand  -  include recall, application, and analysis questions.');
        rules.push('  * UNIQUENESS  -  CRITICAL: Every question must test a DIFFERENT concept, skill, or workplace situation. No two questions may overlap, repeat the same idea, or approach the same topic from the same angle. If Q1 covers a concept, Q2 must cover something genuinely different. Do NOT reuse similar scenarios or rephrase the same question.');
        if (levelLine) {
            rules.push('  * ' + levelLine);
        }
        rules.push('');

        rules.push('BLANK LINE RULES  -  CRITICAL:');
        rules.push('  * Separate every question block from the next with exactly ONE blank line.');
        if (headings) {
            rules.push('  * Separate every section heading from the question block below it with exactly ONE blank line.');
            rules.push('  * Separate every question block from the section heading that follows it with exactly ONE blank line.');
        }
        if (modelResponses) {
            rules.push('  * Do NOT add blank lines INSIDE a question block. The block must read:');
            rules.push('      Q1. [question text]');
            rules.push('      Model Response:');
            rules.push('      [answer text]');
            rules.push('    Then ONE blank line, then the next block.');
        } else {
            rules.push('  * Each question is a single line. Blank lines separate blocks, not words.');
        }
        rules.push('');

        rules.push('OUTPUT FORMAT  -  MANDATORY:');
        rules.push('  * Output ONLY the questions' + (headings ? ', section headings,' : '') + (modelResponses ? ' and model responses.' : '.'));
        rules.push('  * Do NOT include any introduction. Do NOT start with "Sure!", "Here are your questions:", "Of course!" or any similar preamble.');
        rules.push('  * Do NOT include any closing text such as "I hope this helps", "Let me know if you need changes", or any summary.');
        rules.push('  * Do NOT use Markdown formatting  -  no bold (**), no headers (#), no bullet points (-, *), no code blocks.');
        rules.push('  * Plain text only. Every character you output should be part of a question block or section heading.');

        // -- Assemble full prompt ----------------------------------------------
        var lines = [];
        lines.push('You are helping an Australian RTO (Registered Training Organisation) teacher create knowledge assessment questions for a Moodle quiz.');
        lines.push('');
        if (hasNonEssay) {
            lines.push('TASK: Generate ' + count + ' assessment questions on the topic: "' + topic + '".');
            lines.push('Question types to include (the AI Quiz Maker plugin will automatically format these for Moodle): ' + selectedTypeNames.join(', ') + '.');
            // v3.16.46 FIX-AQM-TYPE-DIST: Enforce per-type counts explicitly so the
            // AI does not default to one dominant type. Without this instruction,
            // GPT-4 generates 80 - 100% of one type even when multiple types are selected.
            if (qtypes.length > 1) {
                var _perType  = Math.floor(count / qtypes.length);
                var _rem      = count % qtypes.length;
                var _typeCounts = qtypes.map(function(t, idx) {
                    return (_perType + (idx < _rem ? 1 : 0)) + '  x  ' + (typeLabels[t] || t);
                });
                lines.push('TYPE DISTRIBUTION  -  MANDATORY: You MUST generate exactly these counts: ' + _typeCounts.join(', ') + '. Total must equal ' + count + '. Do not generate more or fewer of any type.');
            }
            lines.push('Write clear question TEXT only  -  numbered Q1., Q2., etc. The plugin handles formatting and answer choices automatically.');
        } else {
            lines.push('TASK: Generate ' + count + ' written-response essay questions on the topic: "' + topic + '".');
        }
        if (levelLine) {
            lines.push(levelLine);
        }
        lines.push('');
        lines.push('====================================================');
        lines.push('EXACT OUTPUT FORMAT  -  follow this example precisely:');
        lines.push('====================================================');
        lines.push('');
        lines.push(exampleBlock);
        lines.push('');
        lines.push('... continue in this exact format for all ' + count + ' questions ...');
        lines.push('');
        lines.push('====================================================');
        lines.push('RULES  -  read ALL rules before generating output:');
        lines.push('====================================================');
        lines.push('');
        lines.push(rules.join('\n'));
        lines.push('');
        lines.push('====================================================');
        if (hasNonEssay) {
            lines.push('Now generate ' + count + ' question texts on "' + topic + '"' +
                (levelLine ? ' at ' + level + ' level' : '') + '.');
            lines.push('Types selected: ' + selectedTypeNames.join(', ') + '. Write one question text per block  -  the plugin will generate the answer choices and structure automatically.');
        } else {
            lines.push('Now generate ' + count + ' questions on "' + topic + '"' +
                (levelLine ? ' at ' + level + ' level' : '') + '.');
        }
        lines.push('Begin your output immediately with ' +
            (headings ? 'the first section heading line (e.g. "Part A: ...")' : '"Q1."') +
            '. No preamble.');
        lines.push('====================================================');

        return lines.join('\n');
    }

    /**
     * Strip leading question-number prefixes from a question text string.
     * Removes formats like: "Q1.", "Q1:", "1.", "1)", "Question 1:", "Question 1."
     * so that the question text stored in Moodle does not contain the numbering prefix.
     * @param {string} text Raw question text that may start with a number prefix.
     * @return {string} Cleaned question text.
     */
    function stripQuestionPrefix(text) {
        if (!text) { return text; }
        // Match: Q1. / Q1: / Q1 / q1. etc., or 1. / 1) / 1: etc., or "Question 1." / "Question 1:"
        return text.replace(/^(?:Question\s+\d+|Q\d+)\s*[.:)]\s*/i, '')
                   .replace(/^\d+\s*[.:)]\s+/, '');
    }

    /**
     * Generate assessment materials (rubric, sample answer, grader info) for user-provided questions.
     * Supports section headings (Part A, Section 1, etc.) which become Moodle description cards.
     */
    function generateFromOwnQuestions() {
        // Parse textarea  -  supports simple line-by-line format and block format with "Model Response:" sections.
        var rawText = $('#own-questions-input').val() || '';
        var allLines = rawText.split('\n').map(function(l) { return l.trim(); }).filter(function(l) { return l.length > 0; });

        if (allLines.length === 0) {
            showAlert('warning', strings.error_missing_criteria || 'No questions entered',
                strings.ownquestions_empty_error || 'Please enter at least one question, one per line.');
            return;
        }

        // Fix 3: Validate minimum meaningful content (at least 20 chars total)
        if (rawText.trim().length < 20) {
            showAlert('warning', strings.error_missing_criteria || 'Content too short',
                'Please enter at least one complete question (minimum 20 characters).');
            return;
        }

        // Detect whether the text uses the "Model Response:" block format.
        var MODEL_RESPONSE_RE = /^model\s+response\s*:?\s*$/i;
        var hasModelResponses = allLines.some(function(l) { return MODEL_RESPONSE_RE.test(l); });

        // orderedItems = [{type:'heading',text:...} | {type:'question',text:...,modelAnswer:...,questionIndex:n}]
        var orderedItems = [];
        var questionItems = []; // [{text, modelAnswer}]

        if (hasModelResponses) {
            // Block-based parsing: blocks are separated by blank lines.
            // Each block may contain: question text, then "Model Response:", then model answer.
            var rawBlocks = rawText.split(/\n\s*\n/).map(function(b) { return b.trim(); }).filter(function(b) { return b.length > 0; });
            rawBlocks.forEach(function(block) {
                var bLines = block.split('\n').map(function(l) { return l.trim(); }).filter(function(l) { return l.length > 0; });
                if (bLines.length === 0) return;
                // Find "Model Response:" marker (never on the very first line)
                var mrIdx = -1;
                for (var i = 1; i < bLines.length; i++) {
                    if (MODEL_RESPONSE_RE.test(bLines[i])) { mrIdx = i; break; }
                }
                if (mrIdx !== -1) {
                    var qText = stripQuestionPrefix(bLines.slice(0, mrIdx).join(' ').trim());
                    var mAnswer = bLines.slice(mrIdx + 1).join('\n').trim();
                    if (qText) {
                        orderedItems.push({type: 'question', text: qText, modelAnswer: mAnswer, questionIndex: questionItems.length});
                        questionItems.push({text: qText, modelAnswer: mAnswer});
                    }
                } else {
                    // Scan each line: heading lines become description cards, others accumulate as question text.
                    var pendingQLines = [];
                    bLines.forEach(function(bLine) {
                        if (isHeadingLine(bLine)) {
                            // Flush any accumulated question lines first.
                            if (pendingQLines.length) {
                                var qt = stripQuestionPrefix(pendingQLines.join(' ').trim());
                                if (qt) {
                                    orderedItems.push({type: 'question', text: qt, modelAnswer: '', questionIndex: questionItems.length});
                                    questionItems.push({text: qt, modelAnswer: ''});
                                }
                                pendingQLines = [];
                            }
                            orderedItems.push({type: 'heading', text: bLine});
                        } else {
                            pendingQLines.push(bLine);
                        }
                    });
                    // Flush remaining non-heading lines as a question.
                    if (pendingQLines.length) {
                        var qt = stripQuestionPrefix(pendingQLines.join(' ').trim());
                        if (qt) {
                            orderedItems.push({type: 'question', text: qt, modelAnswer: '', questionIndex: questionItems.length});
                            questionItems.push({text: qt, modelAnswer: ''});
                        }
                    }
                }
            });
        } else {
            // Original line-by-line parsing (backward compatible  -  no Model Response markers present).
            allLines.forEach(function(line) {
                if (isHeadingLine(line)) {
                    orderedItems.push({type: 'heading', text: line});
                } else {
                    var cleanLine = stripQuestionPrefix(line);
                    orderedItems.push({type: 'question', text: cleanLine, modelAnswer: '', questionIndex: questionItems.length});
                    questionItems.push({text: cleanLine, modelAnswer: ''});
                }
            });
        }

        if (questionItems.length === 0) {
            showAlert('warning', strings.error_missing_criteria || 'No questions entered',
                'Only section headings were detected. Please add at least one question.');
            return;
        }

        if (questionItems.length > 50) {
            showAlert('warning', 'Too many questions', 'Maximum 50 questions at a time. Please reduce the number of questions.');
            return;
        }

        var modelAnswerCount = questionItems.filter(function(qi) { return qi.modelAnswer; }).length;
        log('Generating for ' + questionItems.length + ' own question(s) (' + modelAnswerCount + ' with model answers, headings: ' + (orderedItems.length - questionItems.length) + ')...');
        showLoading(true, (strings.ownquestions_generating || 'Generating for {$a} question(s)...').replace('{$a}', questionItems.length));

        var btn = $('#generate-btn');
        btn.prop('disabled', true);

        var extraInstructions = $('#aiquizmaker-extra-instructions').val() || '';
        var workplaceContextEnabled = isWorkplaceContextEnabled();
        var educationType = $('select[name="education_type"]').val() || 'vet';

        // How many question-type variants to generate per pasted question.
        var typesCount = getMoodleQuestionTypes().length || 1;

        // Build an ordered layout map so PHP can reconstruct the XML with description cards in place.
        // Each question item produces one question slot; type is assigned round-robin server-side.
        // Each heading item produces a single heading slot.
        var orderedLayout = [];
        orderedItems.forEach(function(item) {
            if (item.type === 'heading') {
                orderedLayout.push({type: 'heading', text: item.text});
            } else {
                orderedLayout.push({type: 'question'});
            }
        });

        var postData = {
            action: 'generatefromquestions',
            sesskey: config.sesskey,
            cmid: config.cmid || 0,
            questions: JSON.stringify(questionItems),
            orderedLayout: JSON.stringify(orderedLayout),
            workplaceContextEnabled: workplaceContextEnabled ? '1' : '0',
            educationType: educationType,
            educationLevel: getEducationLevel(),
            extraInstructions: extraInstructions,
            language: config.language || '',
            moodleQuestionTypes: JSON.stringify(getMoodleQuestionTypes()),
            selfMarkingStyles: JSON.stringify(getSelectedSelfMarkingStyles())
        };

        if (workplaceContextEnabled) {
            postData.country = $('select[name="country"]').val();
            postData.state = $('select[name="state"]').val();
            postData.industry = $('select[name="industry"]').val();
            postData.industryDetails = $('input[name="industry_details"]').val();
            postData.jobTitle = getJobTitle();
            postData.jobLevel = getSelectedJobLevels().join(', ');
        } else {
            postData.country = '';
            postData.state = '';
            postData.industry = '';
            postData.industryDetails = '';
            postData.jobTitle = '';
            postData.jobLevel = '';
        }

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: postData,
            dataType: 'json',
            timeout: 180000
        }).done(function(response) {
            log('generatefromquestions response:', response);
            showLoading(false);
            btn.prop('disabled', false);

            if (response.success) {
                // Merge description cards (section headings) back into the results in their
                // original positions, interleaved with the AI-generated question results.
                // Each pasted question produces one result entry (type assigned round-robin server-side).
                var mergedQuestions = [];
                var resultIdx = 0;
                var questionItemIdx = 0;
                orderedItems.forEach(function(item) {
                    if (item.type === 'heading') {
                        mergedQuestions.push({
                            moodleQuestionType: 'description',
                            questionText: item.text,
                            totalMarks: 0,
                            rubric: [],
                            sampleAnswer: [],
                            criteriaReference: ''
                        });
                    } else {
                        var qItem = questionItems[questionItemIdx];
                        if (response.questions[resultIdx]) {
                            var genQ = response.questions[resultIdx];
                            // Preserve the original model answer so regenerate can re-use it.
                            genQ.modelAnswer = (qItem && qItem.modelAnswer) || '';
                            mergedQuestions.push(genQ);
                        }
                        resultIdx++;
                        questionItemIdx++;
                    }
                });

                displayResults(mergedQuestions, response.moodleXml, response.addedToQuiz, response.quizName, response.hasQuizContext);
                updateCredits(response.credits);
                showAlert('success', strings.success_generated, strings.success_generated_message.replace('{$a}', questionItems.length));
            } else {
                if (response.error === 'INSUFFICIENT_CREDITS') {
                    var buyUrl = response.buyUrl || config.buyurl;
                    showAlert('warning', strings.insufficient_credits,
                        strings.insufficient_credits_message + ' <a href="' + buyUrl + '" target="_blank">' + strings.buy_credits + '</a>');
                } else {
                    var errMsg = response.message || response.error || strings.error_generation;
                    showAlert('error', strings.error_generation, errMsg);
                }
            }
        }).fail(function(xhr, status, error) {
            log('generatefromquestions request failed - HTTP', xhr.status, '-', error, '-', xhr.responseText);
            showLoading(false);
            btn.prop('disabled', false);
            var failMsg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : (xhr.status ? 'HTTP ' + xhr.status + ': ' + (error || 'Unknown error') : strings.error_connection);
            showAlert('error', strings.error_connection, failMsg);
        });
    }

    /**
     * Format rubric description text.
     * - Extracts and styles heading (e.g., "Hazard:", "Definition:")
     * - Removes redundant "Award X mark(s) if" prefix
     * @param {string} text The raw description text
     * @return {string} Formatted HTML
     */
    function formatRubricDescription(text) {
        if (!text) return '';
        
        var html = text;
        var heading = '';
        
        // Check for heading pattern (word followed by colon at start)
        var headingMatch = text.match(/^([A-Za-z\s]+):\s*/);
        if (headingMatch) {
            heading = headingMatch[1].trim();
            html = text.substring(headingMatch[0].length);
        }
        
        // Remove "Award X mark(s) if" prefix (case insensitive)
        html = html.replace(/^Award\s+\d+\s+marks?\s+(if\s+)?/i, '');
        
        // Capitalize first letter after cleanup
        if (html.length > 0) {
            html = html.charAt(0).toUpperCase() + html.slice(1);
        }
        
        // Build final HTML with styled heading if present
        if (heading) {
            return '<span class="aiquizmaker-rubric-heading">' + heading + '</span>' + html;
        }
        return html;
    }

    /**
     * Display generated questions and XML.
     * @param {Array} questions Array of question objects.
     * @param {string} moodleXml Moodle XML export string.
     * @param {boolean} addedToQuiz Whether questions were added directly to a quiz.
     * @param {string} quizName The name of the quiz questions were added to.
     * @param {boolean} hasQuizContext Whether we have a quiz context (cmid > 0).
     */
    function displayResults(questions, moodleXml, addedToQuiz, quizName, hasQuizContext) {
        var resultsEl = $('#aiquizmaker-results');
        var listEl = $('#questions-list');
        var xmlEl = $('#moodle-xml');

        // Store quiz context for per-card add button
        currentHasQuizContext = !!hasQuizContext;

        // BUG-QM-GAPSELECT-GROUPS: Normalise gapselect selectOptions to a 0-indexed array here
        // so every downstream path (addtoquiz, XML export, edit modal) always has the correct
        // format. The AI API returns selectOptions as a 1-based object {"1":[...],"2":[...]}.
        // json_decode on the PHP side turns those string keys into PHP integer keys 1 and 2
        // (not 0 and 1), causing $groupnum = $groupidx + 1 to produce groups 2 and 3 instead
        // of 1 and 2  -  so [[1]] and [[2]] in the question text find empty groups and Moodle
        // displays identical choices in every blank. Normalising to [0]=>[...], [1]=>[...] here
        // fixes it before the data ever reaches PHP.
        questions = questions.map(function(q) {
            if (q.moodleQuestionType === 'gapselect' && q.selectOptions && !Array.isArray(q.selectOptions)) {
                var soKeys = Object.keys(q.selectOptions).map(Number).sort(function(a, b) { return a - b; });
                q.selectOptions = soKeys.map(function(k) { return q.selectOptions[k]; });
            }
            return q;
        });

        // Store questions for creation
        lastGeneratedQuestions = questions;

        listEl.empty();

        // Fix 1: Selection toolbar  -  lets teachers choose which questions to add.
        // v3.16.67 FIX: All items (questions AND section headings) now have checkboxes,
        // so selectableCount is the full list length.  Previously descriptions were
        // excluded from the count but still included in lastGeneratedQuestions.length,
        // causing "4 of 6 selected" mismatches whenever headings were present.
        var selectableCount = questions.length;
        listEl.append(
            '<div id="aiquizmaker-select-toolbar" style="display:flex;align-items:center;gap:12px;padding:8px 0 12px 0;border-bottom:1px solid rgba(0,0,0,0.1);margin-bottom:12px;">' +
            '<label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.875rem;font-weight:500;user-select:none;">' +
            '<input type="checkbox" id="aiquizmaker-select-all-chk" checked>' +
            '<span>Select all / none</span>' +
            '</label>' +
            '<span id="aiquizmaker-selection-count" style="font-size:0.875rem;opacity:0.65;">' + selectableCount + ' of ' + selectableCount + ' selected</span>' +
            '</div>'
        );

        // v3.16.67 FIX: Compute a running question number that skips section headings
        // (description items).  Previously index+1 was used, so the first question
        // after a section heading got "Question 2" instead of "Question 1".
        var questionCounter = 0;
        questions.forEach(function(q, index) {
            var isDesc = (q.moodleQuestionType || 'essay') === 'description';
            var questionNum = 0;
            if (!isDesc) {
                questionCounter++;
                questionNum = questionCounter;
            }
            listEl.append(buildQuestionCard(q, index, questionNum));
        });

        xmlEl.val(moodleXml);

        // Build the create section
        var createSectionHtml;
        if (addedToQuiz) {
            // Questions already added to quiz - show success message
            createSectionHtml = '<div class="aiquizmaker-section aiquizmaker-success-section">' +
                '<div class="aiquizmaker-quiz-success">' +
                '<svg class="em-icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                '<div class="aiquizmaker-quiz-success-text">' +
                '<h4>Questions Added to Quiz!</h4>' +
                '<p>All ' + questions.length + ' questions have been added directly to <strong>' + (quizName || 'your quiz') + '</strong>.</p>' +
                '</div></div>' +
                '<div class="em-flex em-flex-wrap em-gap-2" style="margin-top: 16px;">' +
                '<a href="' + M.cfg.wwwroot + '/mod/quiz/edit.php?cmid=' + config.cmid + '" class="aiquizmaker-btn aiquizmaker-btn-primary">' +
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></svg>' +
                ' Return to Quiz</a>' +
                '<button type="button" id="download-excel-inline-btn" class="aiquizmaker-btn aiquizmaker-btn-secondary">' +
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>' +
                '<line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>' +
                ' Download Criteria Mapping</button>' +
                '</div>' +
                '</div>';
        } else if (hasQuizContext) {
            // Quiz context - show "Add to Quiz" button
            createSectionHtml = '<div class="aiquizmaker-section">' +
                '<h4 class="aiquizmaker-section-title">' +
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M12 5v14M5 12h14"/></svg>' +
                ' Add to Quiz</h4>' +
                '<p class="em-text-muted" style="margin-bottom: 12px;">Review and edit questions above, then add them to <strong>' + (quizName || 'your quiz') + '</strong>:</p>' +
                '<div class="em-flex em-flex-wrap em-gap-2">' +
                '<button type="button" id="add-to-quiz-btn" class="aiquizmaker-btn aiquizmaker-btn-primary">' +
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M12 5v14M5 12h14"/></svg>' +
                ' Add ' + questions.length + ' of ' + questions.length + ' to Quiz</button>' +
                '<button type="button" id="download-excel-inline-btn" class="aiquizmaker-btn aiquizmaker-btn-secondary">' +
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>' +
                '<line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>' +
                ' Download Criteria Mapping</button>' +
                '</div></div>';
        } else {
            // No quiz context - show question bank option
            createSectionHtml = '<div class="aiquizmaker-section">' +
                '<h4 class="aiquizmaker-section-title">' +
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M12 5v14M5 12h14"/></svg>' +
                ' Add to Question Bank</h4>' +
                '<p class="em-text-muted" style="margin-bottom: 12px;">Review and edit questions above, then create them in your Moodle question bank:</p>' +
                '<div class="em-flex em-flex-wrap em-gap-2">' +
                buildCategoryDropdown() +
                '<button type="button" id="create-questions-btn" class="aiquizmaker-btn aiquizmaker-btn-primary">' +
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M12 5v14M5 12h14"/></svg>' +
                ' Create in Moodle</button>' +
                '</div></div>';
        }

        $('#create-questions-section').html(createSectionHtml);

        resultsEl.fadeIn(200);

        $('html, body').animate({
            scrollTop: resultsEl.offset().top - 100
        }, 500);
    }

    /**
     * Build HTML for a single question card with edit/regenerate buttons.
     * @param {Object} q Question object.
     * @param {number} index Question index in the full questions array (including descriptions).
     * @param {number} questionNum 1-based question number counting only non-description items.
     *                             Pass 0 for description cards (they have no displayed number).
     * @return {string} HTML string.
     */
    function buildQuestionCard(q, index, questionNum) {
        var qtype = q.moodleQuestionType || 'essay';

        // v3.16.67 FIX: Description cards (section headings) now include a checkbox so they:
        //  (a) are treated as selectable items (select all / none works for them too),
        //  (b) are sent to Moodle in correct sequence when the teacher clicks Add to Quiz,
        //  (c) are counted correctly in the "X of Y selected" and "Add X of Y to Quiz" labels.
        // Previously they had no checkbox, so they were always excluded from getSelectedQuestions()
        // and never inserted into the Moodle quiz  -  even though the PHP addtoquiz handler has
        // supported description type since v2.0.0.
        if (qtype === 'description') {
            return '<div class="aiquizmaker-description-card" data-question-idx="' + index + '">' +
                '<label class="aiquizmaker-question-select-label" title="Select this section heading" style="flex-shrink:0;">' +
                '<input type="checkbox" class="aiquizmaker-question-checkbox" data-idx="' + index + '" checked>' +
                '</label>' +
                '<div class="aiquizmaker-description-icon">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">' +
                '<path d="M4 6h16M4 12h10M4 18h7"/></svg>' +
                '</div>' +
                '<div class="aiquizmaker-description-content">' +
                '<span class="aiquizmaker-description-text">' + decodeHtmlEntities(q.questionText) + '</span>' +
                '</div>' +
                '<span class="aiquizmaker-qtype-badge aiquizmaker-qtype-description">Description</span>' +
                '</div>';
        }

        // For matching, always derive marks from actual pair count (belt-and-suspenders over server-returned totalMarks).
        var totalMarks;
        if (qtype === 'matching' && Array.isArray(q.matchPairs) && q.matchPairs.length > 0) {
            totalMarks = q.matchPairs.length;
        } else if (qtype === 'gapselect') {
            var gapGroupsCount = Array.isArray(q.selectOptions) ? q.selectOptions.length :
                (q.selectOptions && typeof q.selectOptions === 'object' ? Object.keys(q.selectOptions).length : 0);
            totalMarks = q.totalMarks || (gapGroupsCount > 0 ? gapGroupsCount : 1);
        } else {
            totalMarks = q.totalMarks || (qtype === 'multichoice' || qtype === 'truefalse' || qtype === 'shortanswer' ? 1 : 3);
        }
        var marksLabel = totalMarks === 1 ? '1 ' + strings.mark_singular : totalMarks + ' ' + strings.marks_label;

        // Type badge
        var typeBadgeMap = {
            'essay':       'Essay',
            'multichoice': 'Multiple Choice',
            'truefalse':   'True / False',
            'matching':    'Matching',
            'gapselect':   'Select Missing Words',
            'shortanswer': 'Fill in the Blank'
        };
        var typeBadgeLabel = typeBadgeMap[qtype] || 'Essay';
        var typeBadge = '<span class="aiquizmaker-qtype-badge aiquizmaker-qtype-' + qtype + '">' + typeBadgeLabel + '</span>';

        // Build type-specific body
        var bodyHtml = '<div class="aiquizmaker-question-text">' + decodeHtmlEntities(q.questionText) + '</div>';

        if (qtype === 'multichoice') {
            var choices = Array.isArray(q.choices) ? q.choices : [];
            bodyHtml += '<div class="aiquizmaker-mc-choices">';
            choices.forEach(function(choice, ci) {
                var isCorrect = choice.isCorrect ? ' aiquizmaker-mc-correct' : '';
                var tick = choice.isCorrect
                    ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>'
                    : '<span style="display:inline-block;width:14px;height:14px;"></span>';
                bodyHtml += '<div class="aiquizmaker-mc-choice' + isCorrect + '">' + tick + ' ' + String.fromCharCode(65 + ci) + '. ' +
                    decodeHtmlEntities(choice.text || '') + '</div>';
            });
            bodyHtml += '</div>';
            if (q.explanation) {
                bodyHtml += '<div class="aiquizmaker-explanation"><strong>Explanation:</strong> ' + decodeHtmlEntities(q.explanation) + '</div>';
            }

        } else if (qtype === 'truefalse') {
            var correctAnswer = q.correctAnswer;
            var trueClass  = correctAnswer  ? ' aiquizmaker-mc-correct' : '';
            var falseClass = !correctAnswer ? ' aiquizmaker-mc-correct' : '';
            var tick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>';
            var blank = '<span style="display:inline-block;width:14px;height:14px;"></span>';
            bodyHtml += '<div class="aiquizmaker-mc-choices">' +
                '<div class="aiquizmaker-mc-choice' + trueClass  + '">' + (correctAnswer  ? tick : blank) + ' True</div>' +
                '<div class="aiquizmaker-mc-choice' + falseClass + '">' + (!correctAnswer ? tick : blank) + ' False</div>' +
                '</div>';
            if (q.explanation) {
                bodyHtml += '<div class="aiquizmaker-explanation"><strong>Explanation:</strong> ' + decodeHtmlEntities(q.explanation) + '</div>';
            }

        } else if (qtype === 'matching') {
            var pairs = Array.isArray(q.matchPairs) ? q.matchPairs : [];
            bodyHtml += '<table class="aiquizmaker-match-table"><thead><tr>' +
                '<th>Term / Prompt</th><th>Match</th></tr></thead><tbody>';
            pairs.forEach(function(pair) {
                bodyHtml += '<tr><td>' + decodeHtmlEntities(pair.subquestion || '') + '</td>' +
                    '<td>' + decodeHtmlEntities(pair.subanswer || '') + '</td></tr>';
            });
            bodyHtml += '</tbody></table>';
            if (q.explanation) {
                bodyHtml += '<div class="aiquizmaker-explanation"><strong>Explanation:</strong> ' + decodeHtmlEntities(q.explanation) + '</div>';
            }

        } else if (qtype === 'gapselect') {
            // v3.16.60: TWO-part format  -  show scenario as question context, last part as gap sentence.
            // Backward-compatible: old 3-part format (scenario\n\nprompt\n\nsentence) also handled  - 
            // gapParts[last] is always the sentence with [[n]] gaps, gapParts[0] is always the scenario.
            // Removes the hardcoded "Complete the sentence..." instruction in favour of the scenario text.
            var gapQText = (q.questionText || '').trim();
            var gapParts = gapQText.indexOf('\n\n') !== -1 ? gapQText.split('\n\n') : [];
            var gapScenario = gapParts.length >= 2 ? gapParts[0].trim() : '';
            var gapSentence = gapParts.length >= 2 ? gapParts[gapParts.length - 1].trim() : gapQText;
            bodyHtml = (gapScenario
                    ? '<div class="aiquizmaker-gap-scenario">' + decodeHtmlEntities(gapScenario) + '</div>'
                    : '') +
                (gapSentence
                    ? '<div class="aiquizmaker-gap-answer-label"><strong>Answer (with blanks):</strong></div>' +
                      '<div class="aiquizmaker-question-text">' + decodeHtmlEntities(gapSentence) + '</div>'
                    : '');
            // Render gap groups: selectOptions is an array-of-arrays or object keyed by group number
            var rawSO = q.selectOptions || {};
            var gsGroups = [];
            if (Array.isArray(rawSO)) {
                gsGroups = rawSO;
            } else {
                var soKeys = Object.keys(rawSO).map(Number).sort(function(a, b) { return a - b; });
                soKeys.forEach(function(k) { gsGroups.push(rawSO[k]); });
            }
            if (gsGroups.length > 0) {
                bodyHtml += '<div class="aiquizmaker-gap-groups-preview">';
                gsGroups.forEach(function(opts, gi) {
                    if (!Array.isArray(opts) || opts.length === 0) { return; }
                    var correct = decodeHtmlEntities(opts[0] || '');
                    var distractors = opts.slice(1).map(function(o) { return decodeHtmlEntities(o); });
                    bodyHtml += '<div class="aiquizmaker-gap-preview-row">' +
                        '<span class="aiquizmaker-gap-preview-num">[[' + (gi + 1) + ']]</span>' +
                        '<span class="aiquizmaker-gap-preview-correct">' + correct + '</span>';
                    if (distractors.length > 0) {
                        bodyHtml += '<span class="aiquizmaker-gap-preview-distractors">+ ' + distractors.join(', ') + '</span>';
                    }
                    bodyHtml += '</div>';
                });
                bodyHtml += '</div>';
            }
            if (q.generalFeedback) {
                bodyHtml += '<div class="aiquizmaker-explanation"><strong>Feedback:</strong> ' + decodeHtmlEntities(q.generalFeedback) + '</div>';
            }

        } else if (qtype === 'shortanswer') {
            var accepted = Array.isArray(q.acceptedAnswers) ? q.acceptedAnswers : [];
            if (q.blankSentence) {
                var blankDisplay = decodeHtmlEntities(q.blankSentence).replace(/_{2,}/g, '<span class="aiquizmaker-blank-placeholder">________</span>');
                bodyHtml += '<div class="aiquizmaker-blank-sentence"><strong>Complete the sentence:</strong> ' + blankDisplay + '</div>';
            }
            bodyHtml += '<div class="aiquizmaker-accepted-answers"><strong>Accepted answers:</strong> ' +
                accepted.map(function(a) { return '<code>' + decodeHtmlEntities(a) + '</code>'; }).join(', ') +
                '</div>';
            if (q.explanation) {
                bodyHtml += '<div class="aiquizmaker-explanation"><strong>Explanation:</strong> ' + decodeHtmlEntities(q.explanation) + '</div>';
            }

        } else {
            // Essay  -  dynamic rubric display
            var rubricHtml = '';
            if (Array.isArray(q.rubric)) {
                q.rubric.forEach(function(r, rIdx) {
                    var markLabel = r.marks === 1 ? strings.one_mark : r.marks + ' ' + strings.marks_label;
                    rubricHtml += '<div class="aiquizmaker-rubric-item" data-rubric-idx="' + rIdx + '">' +
                        '<span class="aiquizmaker-rubric-mark">' + markLabel + '</span>' +
                        '<span class="aiquizmaker-rubric-desc">' + formatRubricDescription(r.description) + '</span></div>';
                });
            } else if (q.rubric && typeof q.rubric === 'object') {
                ['hazard', 'example', 'control'].forEach(function(key) {
                    if (q.rubric[key]) {
                        rubricHtml += '<div class="aiquizmaker-rubric-item"><span class="aiquizmaker-rubric-mark">' + strings.one_mark + '</span>' +
                            '<span class="aiquizmaker-rubric-desc">' + formatRubricDescription(q.rubric[key]) + '</span></div>';
                    }
                });
            }
            bodyHtml += '<div class="aiquizmaker-rubric">' +
                '<div class="aiquizmaker-rubric-title">' + strings.marking_rubric + '</div>' +
                rubricHtml +
                '</div>';
        }

        // v3.16.67 FIX: Use questionNum (counts only non-description items, starting at 1)
        // instead of index+1 (which counted ALL items including section headings, causing
        // the first question after a heading to display "Question 2" instead of "Question 1").
        var html = '<div class="aiquizmaker-question-card" data-question-idx="' + index + '">' +
            '<div class="aiquizmaker-question-header">' +
            '<label class="aiquizmaker-question-select-label" title="Select this question">' +
            '<input type="checkbox" class="aiquizmaker-question-checkbox" data-idx="' + index + '" checked>' +
            '</label>' +
            '<span class="aiquizmaker-question-number">' + strings.question + ' ' + questionNum + '</span>' +
            typeBadge +
            '<div class="aiquizmaker-question-actions">' +
            '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-edit-question-btn" title="Edit question" data-idx="' + index + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">' +
            '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
            '</button>' +
            '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-regenerate-btn" title="Regenerate this question" data-idx="' + index + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">' +
            '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>' +
            '</button>' +
            '<span class="aiquizmaker-question-marks">' + marksLabel + '</span>' +
            '</div>' +
            '</div>' +
            bodyHtml +
            (q.criteriaReference ? '<div class="aiquizmaker-criteria-ref">' + strings.criteria + ': ' + decodeHtmlEntities(q.criteriaReference) + '</div>' : '') +
            '</div>';

        return html;
    }

    /**
     * Fix 1: Return only the questions the teacher has ticked in the selection checkboxes.
     * v3.16.44: Also ensure gapselect questions always carry questionText so the
     * PHP addtoquiz/createquestions DB creators receive the [[n]] sentence.
     * @return {Array} Filtered subset of lastGeneratedQuestions.
     */
    function getSelectedQuestions() {
        var selectedIdxs = [];
        $('.aiquizmaker-question-checkbox:checked').each(function() {
            selectedIdxs.push(parseInt($(this).data('idx'), 10));
        });
        return lastGeneratedQuestions.filter(function(q, i) {
            return selectedIdxs.indexOf(i) !== -1;
        }).map(function(q) {
            // Defensive: if a gapselect question somehow lost its questionText, restore a safe fallback.
            if (q.moodleQuestionType === 'gapselect' && !q.questionText) {
                return Object.assign({}, q, { questionText: 'Complete the sentence by selecting the correct words.' });
            }
            return q;
        });
    }

    /**
     * v3.16.67 FIX: Refresh the "Add X of Y to Quiz" button label and the selection count.
     *
     * Previously:
     *  - total = lastGeneratedQuestions.length  (includes section heading descriptions)
     *  - selected = .aiquizmaker-question-checkbox:checked  (EXCLUDED descriptions  -  no checkbox)
     *   ->  Result: "4 of 6 selected" / "Add 4 of 6 Questions to Quiz" mismatch when headings exist.
     *
     * Now:
     *  - Description cards have a checkbox (same class), so both total and selected count
     *    all items consistently.  "6 of 6 selected" / "Add 6 of 6 to Quiz" when all selected.
     *  - Label format is always "Add X of Y to Quiz" (includes headings in the count, since
     *    they will be inserted into Moodle in sequence).
     */
    function updateAddToQuizButtonLabel() {
        var total = lastGeneratedQuestions.length;
        var selected = $('.aiquizmaker-question-checkbox:checked').length;
        var svgPlus = '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>';
        var label = 'Add ' + selected + ' of ' + total + ' to Quiz';
        $('#add-to-quiz-btn').html(svgPlus + ' ' + label);
        $('#aiquizmaker-selection-count').text(selected + ' of ' + total + ' selected');
        var allChk = $('#aiquizmaker-select-all-chk');
        if (selected === 0) {
            allChk.prop('checked', false).prop('indeterminate', false);
        } else if (selected === total) {
            allChk.prop('checked', true).prop('indeterminate', false);
        } else {
            allChk.prop('checked', false).prop('indeterminate', true);
        }
    }

    /**
     * Add questions to quiz (called when user clicks "Add to Quiz" button).
     */
    function addQuestionsToQuiz() {
        if (lastGeneratedQuestions.length === 0) {
            showAlert('error', 'No questions', 'Please generate questions first.');
            return;
        }

        // Fix 1: Only add teacher-selected questions.
        var selectedQuestions = getSelectedQuestions();
        if (selectedQuestions.length === 0) {
            showAlert('warning', 'No questions selected', 'Please tick at least one question using the checkboxes before adding.');
            return;
        }

        var btn = $('#add-to-quiz-btn');
        btn.prop('disabled', true).html(
            '<svg class="em-spinner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
            '<circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="12"/></svg> Adding...'
        );

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'addtoquiz',
                sesskey: config.sesskey,
                cmid: config.cmid,
                questions: JSON.stringify(selectedQuestions)
            },
            dataType: 'json',
            timeout: 60000
        }).done(function(response) {
            if (response.success) {
                // F3: Surface skip/error details to teacher after addtoquiz so they know if questions were omitted.
                var atqSkipDetail = '';
                if (response.skipped && response.skipped.length > 0) {
                    atqSkipDetail = response.skipped.length + ' question(s) skipped (invalid content). ';
                }
                if (response.errors && response.errors.length > 0) {
                    atqSkipDetail += 'Errors: ' + response.errors.join('; ');
                }
                showAlert('success', 'Questions Added', response.added + ' question(s) added to ' + (response.quizName || 'your quiz') + '. ' + atqSkipDetail);
                $('#create-questions-section').html(
                    '<div class="aiquizmaker-section aiquizmaker-success-section">' +
                    '<div class="aiquizmaker-quiz-success">' +
                    '<svg class="em-icon-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                    '<div class="aiquizmaker-quiz-success-text">' +
                    '<h4>Questions Added to Quiz!</h4>' +
                    '<p>' + response.added + ' questions added to <strong>' + (response.quizName || 'your quiz') + '</strong>.' +
                    (atqSkipDetail ? ' <em>' + atqSkipDetail + '</em>' : '') + '</p>' +
                    '</div></div>' +
                    '<div class="em-flex em-flex-wrap em-gap-2" style="margin-top: 16px;">' +
                    '<a href="' + M.cfg.wwwroot + '/mod/quiz/edit.php?cmid=' + config.cmid + '" class="aiquizmaker-btn aiquizmaker-btn-primary">' +
                    '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></svg>' +
                    ' Return to Quiz</a>' +
                    '<button type="button" id="download-excel-inline-btn" class="aiquizmaker-btn aiquizmaker-btn-secondary">' +
                    '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>' +
                    '<line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>' +
                    ' Download Criteria Mapping</button>' +
                    '</div></div>'
                );
            } else {
                var atqErrMsg = response.error || 'Failed to add questions to quiz.';
                if (response.skipped && response.skipped.length > 0) {
                    atqErrMsg += ' Skipped: ' + response.skipped.join('; ');
                }
                showAlert('error', 'Error', atqErrMsg);
                btn.prop('disabled', false).html(
                    '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M12 5v14M5 12h14"/></svg> Add ' + selectedQuestions.length + ' Questions to Quiz'
                );
            }
        }).fail(function() {
            showAlert('error', 'Error', 'Connection failed. Please try again.');
            btn.prop('disabled', false).html(
                '<svg class="em-icon-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M12 5v14M5 12h14"/></svg> Add ' + selectedQuestions.length + ' Questions to Quiz'
            );
        });
    }

    /**
     * Add a single question (by index) directly to the quiz.
     * Shows inline feedback on the card button.
     * @param {number} index Question index in lastGeneratedQuestions.
     */
    function addSingleQuestionToQuiz(index) {
        var q = lastGeneratedQuestions[index];
        if (!q || q.moodleQuestionType === 'description') { return; }

        var cardEl = $('#questions-list .aiquizmaker-question-card[data-question-idx="' + index + '"]');
        var btn = cardEl.find('.aiquizmaker-add-single-btn');
        btn.prop('disabled', true).addClass('is-loading');

        // v3.16.44: Defensive  -  ensure gapselect questionText is always present.
        var singleQPayload = (q.moodleQuestionType === 'gapselect' && !q.questionText)
            ? Object.assign({}, q, { questionText: 'Complete the sentence by selecting the correct words.' })
            : q;

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'addtoquiz',
                sesskey: config.sesskey,
                cmid: config.cmid,
                questions: JSON.stringify([singleQPayload])
            },
            dataType: 'json',
            timeout: 60000
        }).done(function(response) {
            btn.prop('disabled', false).removeClass('is-loading');
            if (response.success && response.added > 0) {
                showAlert('success', 'Question Added', 'Question ' + (index + 1) + ' added to ' + (response.quizName || 'your quiz') + '.');
                btn.addClass('aiquizmaker-add-single-done').attr('title', 'Added to quiz');
                btn.html(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">' +
                    '<polyline points="20 6 9 17 4 12"/></svg>'
                );
            } else {
                showAlert('error', 'Error', response.error || 'Failed to add question to quiz.');
            }
        }).fail(function() {
            btn.prop('disabled', false).removeClass('is-loading');
            showAlert('error', 'Error', 'Connection failed. Please try again.');
        });
    }

    /**
     * Escape a string for safe use in an HTML attribute value (double-quoted).
     * @param {*} str
     * @return {string}
     */
    function escapeAttr(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /**
     * Build an MCQ choice edit row.
     * @param {Object} choice  {text, isCorrect, feedback}
     * @param {number} ci      0-based index
     * @param {number} total   total number of choices (for remove-button logic)
     * @return {string}
     */
    function buildChoiceEditRow(choice, ci, total) {
        var letter      = String.fromCharCode(65 + ci);
        var checkedAttr = choice.isCorrect ? ' checked' : '';
        var canRemove   = total > 2;
        var removeBtn   = canRemove
            ? '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-choice-btn" title="Remove choice">' +
              '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
              '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
            : '<span class="aiquizmaker-choice-remove-placeholder"></span>';
        return '<div class="aiquizmaker-edit-choice-row" data-choice-idx="' + ci + '">' +
            '<span class="aiquizmaker-edit-choice-letter">' + letter + '</span>' +
            '<input type="radio" name="edit-mc-correct" class="aiquizmaker-edit-choice-correct" title="Mark as correct answer"' + checkedAttr + '>' +
            '<input type="text" class="aiquizmaker-edit-choice-text" placeholder="Choice text..." value="' + escapeAttr(choice.text) + '">' +
            removeBtn +
            '</div>';
    }

    /**
     * Re-letter choice rows after add/remove.
     */
    function reindexChoiceLetters() {
        $('#edit-choices-container .aiquizmaker-edit-choice-row').each(function(ci) {
            $(this).attr('data-choice-idx', ci);
            $(this).find('.aiquizmaker-edit-choice-letter').text(String.fromCharCode(65 + ci));
        });
    }

    /**
     * Build a matching pair edit row.
     * @param {Object} pair  {subquestion, subanswer}
     * @param {number} pi    0-based index
     * @return {string}
     */
    function buildPairEditRow(pair, pi) {
        return '<div class="aiquizmaker-edit-pair-row" data-pair-idx="' + pi + '">' +
            '<div class="aiquizmaker-edit-pair-col">' +
            '<label class="aiquizmaker-edit-pair-label">Term / Prompt</label>' +
            '<input type="text" class="aiquizmaker-edit-pair-subquestion" value="' + escapeAttr(pair.subquestion) + '" placeholder="Term or prompt...">' +
            '</div>' +
            '<div class="aiquizmaker-edit-pair-arrow">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
            '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>' +
            '</div>' +
            '<div class="aiquizmaker-edit-pair-col">' +
            '<label class="aiquizmaker-edit-pair-label">Match / Answer</label>' +
            '<input type="text" class="aiquizmaker-edit-pair-subanswer" value="' + escapeAttr(pair.subanswer) + '" placeholder="Matching answer...">' +
            '</div>' +
            '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-pair-btn" title="Remove pair">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
            '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
            '</div>';
    }

    /**
     * Build a gapselect group edit block.
     * @param {string[]} group  Options array; first item is the correct answer.
     * @param {number}   gi     0-based group index.
     * @return {string}
     */
    function buildGapGroupEditBlock(group, gi) {
        if (!Array.isArray(group) || group.length === 0) { group = ['']; }
        // FIX-QM-GAPSELECT-JUMPBY3: Groups jump by 3 (1, 4, 7) to match the
        // [[1]], [[4]], [[7]] placeholder numbering in the question sentence.
        var groupNum    = gi * 3 + 1;
        var optionsHtml = '';
        group.forEach(function(opt, oi) {
            var badge = oi === 0
                ? '<span class="aiquizmaker-gap-correct-badge">Correct</span>'
                : '<span class="aiquizmaker-gap-distractor-badge">Distractor</span>';
            var removeBtn = oi > 0
                ? '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-gap-opt-btn" title="Remove option">' +
                  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
                  '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
                : '';
            optionsHtml += '<div class="aiquizmaker-edit-gap-option" data-opt-idx="' + oi + '">' +
                badge +
                '<input type="text" class="aiquizmaker-edit-gap-opt-text" value="' + escapeAttr(opt) + '" placeholder="Option text...">' +
                removeBtn +
                '</div>';
        });
        return '<div class="aiquizmaker-edit-gap-group" data-group-idx="' + gi + '">' +
            '<div class="aiquizmaker-gap-group-header"><strong>Gap [[' + groupNum + ']]</strong></div>' +
            '<div class="aiquizmaker-edit-gap-options">' + optionsHtml + '</div>' +
            '<button type="button" class="aiquizmaker-btn aiquizmaker-btn-outline aiquizmaker-add-gap-opt-btn" data-group="' + gi + '">+ Add Distractor</button>' +
            '</div>';
    }

    /**
     * Build a shortanswer accepted-answer edit row.
     * @param {string} ans  Accepted answer text.
     * @param {number} ai   0-based index.
     * @return {string}
     */
    function buildAnswerEditRow(ans, ai) {
        return '<div class="aiquizmaker-edit-answer-row" data-answer-idx="' + ai + '">' +
            '<span class="aiquizmaker-answer-num">' + (ai + 1) + '.</span>' +
            '<input type="text" class="aiquizmaker-edit-answer-text" value="' + escapeAttr(ans) + '" placeholder="Accepted answer...">' +
            '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-answer-btn" title="Remove answer">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
            '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
            '</div>';
    }

    /**
     * Build a single rubric row HTML for the edit modal (v3.14.0)
     * @param {Object} r Rubric item {marks, description}
     * @param {number} rIdx Rubric index
     * @return {string} HTML string
     */
    function buildRubricEditRow(r, rIdx) {
        var marksOptions = '';
        for (var m = 1; m <= 5; m++) {
            marksOptions += '<option value="' + m + '"' + (r.marks === m ? ' selected' : '') + '>' + m + '</option>';
        }
        
        return '<div class="aiquizmaker-edit-rubric-row" data-rubric-idx="' + rIdx + '">' +
            '<div class="aiquizmaker-rubric-row-header">' +
            '<span class="aiquizmaker-rubric-label">Criterion ' + (rIdx + 1) + '</span>' +
            '<div class="aiquizmaker-rubric-controls">' +
            '<select class="aiquizmaker-edit-rubric-marks" title="Marks for this criterion">' + marksOptions + '</select>' +
            '<span class="aiquizmaker-marks-suffix">mark(s)</span>' +
            '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-rubric-btn" title="Remove criterion">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
            '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>' +
            '</div>' +
            '</div>' +
            '<textarea class="aiquizmaker-edit-rubric-desc" rows="2" placeholder="Describe what the student must demonstrate...">' + decodeHtmlEntities(r.description) + '</textarea>' +
            '</div>';
    }

    /**
     * Update total marks display in edit modal (v3.14.0)
     */
    function updateEditModalTotalMarks() {
        var total = 0;
        $('.aiquizmaker-edit-rubric-marks').each(function() {
            total += parseInt($(this).val(), 10) || 0;
        });
        $('#edit-modal-total-marks').text(total);
    }

    /**
     * Re-index rubric rows after add/remove (v3.14.0)
     */
    function reindexRubricRows() {
        $('.aiquizmaker-edit-rubric-row').each(function(idx) {
            $(this).attr('data-rubric-idx', idx);
            $(this).find('.aiquizmaker-rubric-label').text('Criterion ' + (idx + 1));
        });
    }

    /**
     * Open edit modal for a question  -  type-aware (v3.16.17).
     * Shows the correct fields for essay, multichoice, truefalse, matching, gapselect, shortanswer.
     * @param {number} index Question index in lastGeneratedQuestions.
     */
    function openEditModal(index) {
        var q = lastGeneratedQuestions[index];
        if (!q) return;

        var qtype = q.moodleQuestionType || 'essay';

        // --- Question text label varies by type ---
        var textLabelMap = {
            'shortanswer': 'Question Text: <span class="aiquizmaker-field-hint">(the question being asked  -  the fill-in-blank sentence is below)</span>',
            'gapselect': 'Sentence with Gap Placeholders: <span class="aiquizmaker-field-hint">(use [[1]], [[2]] etc. as drop-down placeholders)</span>',
            'matching':  'Question / Instruction Text:',
            'essay':     'Question Text:'
        };
        var textLabel = textLabelMap[qtype] || 'Question Text:';

        var questionTextField =
            '<div class="aiquizmaker-edit-field">' +
            '<label>' + textLabel + '</label>' +
            '<textarea id="edit-question-text" rows="4">' + decodeHtmlEntities(q.questionText) + '</textarea>' +
            '</div>';

        // --- Type-specific body ---
        var typeBodyHtml = '';

        if (qtype === 'essay') {
            if (!Array.isArray(q.rubric)) { q.rubric = []; }
            var totalMarks = q.rubric.reduce(function(sum, r) { return sum + (r.marks || 1); }, 0);
            var rubricFields = '';
            q.rubric.forEach(function(r, rIdx) { rubricFields += buildRubricEditRow(r, rIdx); });
            typeBodyHtml =
                '<div class="aiquizmaker-edit-field">' +
                '<div class="aiquizmaker-rubric-header">' +
                '<label>Marking Criteria:</label>' +
                '<span class="aiquizmaker-total-marks-badge">Total: <strong id="edit-modal-total-marks">' + totalMarks + '</strong> marks</span>' +
                '</div>' +
                '<div id="edit-rubric-container">' + rubricFields + '</div>' +
                '<button type="button" class="aiquizmaker-btn aiquizmaker-btn-outline aiquizmaker-add-rubric-btn" id="add-rubric-btn">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
                '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                ' Add Criterion</button>' +
                '</div>';

        } else if (qtype === 'multichoice') {
            var choices = Array.isArray(q.choices) ? q.choices.slice() : [];
            if (choices.length < 2) {
                choices = [
                    { text: '', isCorrect: true,  feedback: '' },
                    { text: '', isCorrect: false, feedback: '' },
                    { text: '', isCorrect: false, feedback: '' },
                    { text: '', isCorrect: false, feedback: '' }
                ];
            }
            var choicesHtml = '';
            choices.forEach(function(c, ci) { choicesHtml += buildChoiceEditRow(c, ci, choices.length); });
            typeBodyHtml =
                '<div class="aiquizmaker-edit-field">' +
                '<label>Answer Choices: <span class="aiquizmaker-field-hint">(select the radio button next to the correct answer)</span></label>' +
                '<div id="edit-choices-container">' + choicesHtml + '</div>' +
                '<button type="button" class="aiquizmaker-btn aiquizmaker-btn-outline aiquizmaker-add-rubric-btn" id="add-choice-btn">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
                '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                ' Add Choice</button>' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>Explanation / General Feedback:</label>' +
                '<textarea id="edit-explanation" rows="2">' + decodeHtmlEntities(q.explanation || '') + '</textarea>' +
                '</div>';

        } else if (qtype === 'truefalse') {
            var tfCorrect = (q.correctAnswer === true || q.correctAnswer === 'true' || q.correctAnswer === 1);
            var tfTrue    = tfCorrect  ? ' checked' : '';
            var tfFalse   = !tfCorrect ? ' checked' : '';
            typeBodyHtml =
                '<div class="aiquizmaker-edit-field">' +
                '<label>Correct Answer:</label>' +
                '<div class="aiquizmaker-tf-options">' +
                '<label class="aiquizmaker-tf-label"><input type="radio" name="edit-tf-answer" value="true"' + tfTrue + '> True</label>' +
                '<label class="aiquizmaker-tf-label"><input type="radio" name="edit-tf-answer" value="false"' + tfFalse + '> False</label>' +
                '</div>' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>Feedback shown when student answers True:</label>' +
                '<input type="text" id="edit-true-feedback" class="aiquizmaker-edit-text-input" value="' + escapeAttr(q.trueAnswerFeedback || '') + '">' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>Feedback shown when student answers False:</label>' +
                '<input type="text" id="edit-false-feedback" class="aiquizmaker-edit-text-input" value="' + escapeAttr(q.falseAnswerFeedback || '') + '">' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>Explanation / General Feedback:</label>' +
                '<textarea id="edit-explanation" rows="2">' + decodeHtmlEntities(q.explanation || '') + '</textarea>' +
                '</div>';

        } else if (qtype === 'matching') {
            var pairs = Array.isArray(q.matchPairs) ? q.matchPairs.slice() : [];
            if (pairs.length < 2) { pairs = [{ subquestion: '', subanswer: '' }, { subquestion: '', subanswer: '' }]; }
            var pairsHtml = '';
            pairs.forEach(function(pair, pi) { pairsHtml += buildPairEditRow(pair, pi); });
            typeBodyHtml =
                '<div class="aiquizmaker-edit-field">' +
                '<label>Match Pairs: <span class="aiquizmaker-field-hint">(each row is one term and its matching answer)</span></label>' +
                '<div id="edit-pairs-container">' + pairsHtml + '</div>' +
                '<button type="button" class="aiquizmaker-btn aiquizmaker-btn-outline aiquizmaker-add-rubric-btn" id="add-pair-btn">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
                '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                ' Add Pair</button>' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>Explanation / General Feedback:</label>' +
                '<textarea id="edit-explanation" rows="2">' + decodeHtmlEntities(q.explanation || '') + '</textarea>' +
                '</div>';

        } else if (qtype === 'gapselect') {
            var rawOpts = q.selectOptions || {};
            var groups  = [];
            if (Array.isArray(rawOpts)) {
                groups = rawOpts;
            } else {
                // Object keyed by group number (1-based)  -  convert to ordered array
                var gkeys = Object.keys(rawOpts).map(Number).sort(function(a, b) { return a - b; });
                gkeys.forEach(function(k) { groups.push(rawOpts[k]); });
            }
            if (groups.length === 0) { groups = [['']]; }
            var groupsHtml = '';
            groups.forEach(function(grp, gi) { groupsHtml += buildGapGroupEditBlock(grp, gi); });
            typeBodyHtml =
                '<div class="aiquizmaker-edit-field">' +
                '<label>Gap Groups: <span class="aiquizmaker-field-hint">(the first option in each group is the correct answer; add distractors below it)</span></label>' +
                '<div id="edit-gap-groups">' + groupsHtml + '</div>' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>General Feedback:</label>' +
                '<textarea id="edit-general-feedback" rows="2">' + decodeHtmlEntities(q.generalFeedback || '') + '</textarea>' +
                '</div>';

        } else if (qtype === 'shortanswer') {
            var accepted = Array.isArray(q.acceptedAnswers) ? q.acceptedAnswers.slice() : [];
            if (accepted.length === 0) { accepted = ['']; }
            var answersHtml = '';
            accepted.forEach(function(ans, ai) { answersHtml += buildAnswerEditRow(ans, ai); });
            typeBodyHtml =
                '<div class="aiquizmaker-edit-field">' +
                '<label>Fill-in-the-Blank Sentence: <span class="aiquizmaker-field-hint">(use ___ for the blank  -  shown below the question)</span></label>' +
                '<textarea id="edit-blank-sentence" rows="2">' + decodeHtmlEntities(q.blankSentence || '') + '</textarea>' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>Accepted Answers: <span class="aiquizmaker-field-hint">(first is the primary correct answer; all listed answers are accepted)</span></label>' +
                '<div id="edit-answers-container">' + answersHtml + '</div>' +
                '<button type="button" class="aiquizmaker-btn aiquizmaker-btn-outline aiquizmaker-add-rubric-btn" id="add-answer-btn">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
                '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                ' Add Answer</button>' +
                '</div>' +
                '<div class="aiquizmaker-edit-field">' +
                '<label>Explanation / General Feedback:</label>' +
                '<textarea id="edit-explanation" rows="2">' + decodeHtmlEntities(q.explanation || '') + '</textarea>' +
                '</div>';
        }

        var modalHtml =
            '<div class="aiquizmaker-modal-overlay" id="edit-modal-overlay">' +
            '<div class="aiquizmaker-modal aiquizmaker-modal-wide">' +
            '<div class="aiquizmaker-modal-header">' +
            '<h3>Edit Question ' + (index + 1) + '</h3>' +
            '<button type="button" class="aiquizmaker-modal-close" id="close-edit-modal">&times;</button>' +
            '</div>' +
            '<div class="aiquizmaker-modal-body">' +
            questionTextField +
            typeBodyHtml +
            '</div>' +
            '<div class="aiquizmaker-modal-footer">' +
            '<button type="button" class="aiquizmaker-btn aiquizmaker-btn-secondary" id="cancel-edit-modal">Cancel</button>' +
            '<button type="button" class="aiquizmaker-btn aiquizmaker-btn-primary" id="save-edit-modal" data-idx="' + index + '">Save Changes</button>' +
            '</div>' +
            '</div></div>';

        $('body').append(modalHtml);

        // --- Common close handlers ---
        $('#close-edit-modal, #cancel-edit-modal').on('click', function() {
            $('#edit-modal-overlay').remove();
        });
        $('#edit-modal-overlay').on('click', function(e) {
            if (e.target === this) { $('#edit-modal-overlay').remove(); }
        });
        $('#save-edit-modal').on('click', function() {
            saveQuestionEdit(parseInt($(this).data('idx'), 10));
        });

        // --- Essay-specific handlers ---
        if (qtype === 'essay') {
            $('#add-rubric-btn').on('click', function() {
                var newIdx = $('.aiquizmaker-edit-rubric-row').length;
                $('#edit-rubric-container').append(buildRubricEditRow({ marks: 1, description: '' }, newIdx));
                updateEditModalTotalMarks();
                $('#edit-rubric-container .aiquizmaker-edit-rubric-row:last .aiquizmaker-edit-rubric-desc').focus();
            });
            $('#edit-rubric-container').on('click', '.aiquizmaker-remove-rubric-btn', function() {
                if ($('.aiquizmaker-edit-rubric-row').length <= 1) {
                    showAlert('warning', 'Cannot Remove', 'At least one marking criterion is required.');
                    return;
                }
                $(this).closest('.aiquizmaker-edit-rubric-row').remove();
                reindexRubricRows();
                updateEditModalTotalMarks();
            });
            $('#edit-rubric-container').on('change', '.aiquizmaker-edit-rubric-marks', function() {
                updateEditModalTotalMarks();
            });
        }

        // --- MCQ-specific handlers ---
        if (qtype === 'multichoice') {
            $('#add-choice-btn').on('click', function() {
                var count = $('#edit-choices-container .aiquizmaker-edit-choice-row').length;
                $('#edit-choices-container').append(buildChoiceEditRow({ text: '', isCorrect: false, feedback: '' }, count, count + 1));
            });
            $('#edit-choices-container').on('click', '.aiquizmaker-remove-choice-btn', function() {
                if ($('#edit-choices-container .aiquizmaker-edit-choice-row').length <= 2) {
                    showAlert('warning', 'Cannot Remove', 'At least two answer choices are required.');
                    return;
                }
                $(this).closest('.aiquizmaker-edit-choice-row').remove();
                reindexChoiceLetters();
            });
        }

        // --- Matching-specific handlers ---
        if (qtype === 'matching') {
            $('#add-pair-btn').on('click', function() {
                var count = $('#edit-pairs-container .aiquizmaker-edit-pair-row').length;
                $('#edit-pairs-container').append(buildPairEditRow({ subquestion: '', subanswer: '' }, count));
            });
            $('#edit-pairs-container').on('click', '.aiquizmaker-remove-pair-btn', function() {
                if ($('#edit-pairs-container .aiquizmaker-edit-pair-row').length <= 2) {
                    showAlert('warning', 'Cannot Remove', 'At least two pairs are required for matching questions.');
                    return;
                }
                $(this).closest('.aiquizmaker-edit-pair-row').remove();
            });
        }

        // --- Gapselect-specific handlers ---
        if (qtype === 'gapselect') {
            $('#edit-gap-groups').on('click', '.aiquizmaker-add-gap-opt-btn', function() {
                var gi    = parseInt($(this).data('group'), 10);
                var group = $(this).closest('.aiquizmaker-edit-gap-group');
                var count = group.find('.aiquizmaker-edit-gap-option').length;
                var newOpt =
                    '<div class="aiquizmaker-edit-gap-option" data-opt-idx="' + count + '">' +
                    '<span class="aiquizmaker-gap-distractor-badge">Distractor</span>' +
                    '<input type="text" class="aiquizmaker-edit-gap-opt-text" value="" placeholder="Option text...">' +
                    '<button type="button" class="aiquizmaker-btn-icon aiquizmaker-remove-gap-opt-btn" title="Remove option">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">' +
                    '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
                    '</div>';
                group.find('.aiquizmaker-edit-gap-options').append(newOpt);
            });
            $('#edit-gap-groups').on('click', '.aiquizmaker-remove-gap-opt-btn', function() {
                $(this).closest('.aiquizmaker-edit-gap-option').remove();
            });
        }

        // --- Shortanswer-specific handlers ---
        if (qtype === 'shortanswer') {
            $('#add-answer-btn').on('click', function() {
                var count = $('#edit-answers-container .aiquizmaker-edit-answer-row').length;
                $('#edit-answers-container').append(buildAnswerEditRow('', count));
            });
            $('#edit-answers-container').on('click', '.aiquizmaker-remove-answer-btn', function() {
                if ($('#edit-answers-container .aiquizmaker-edit-answer-row').length <= 1) {
                    showAlert('warning', 'Cannot Remove', 'At least one accepted answer is required.');
                    return;
                }
                $(this).closest('.aiquizmaker-edit-answer-row').remove();
            });
        }
    }

    /**
     * Save edited question  -  type-aware (v3.16.17).
     * Reads back the correct fields for each question type.
     * @param {number} index Question index.
     */
    function saveQuestionEdit(index) {
        var q = lastGeneratedQuestions[index];
        if (!q) return;

        var qtype = q.moodleQuestionType || 'essay';

        // Preserve the original questionText before any mutation so we can restore it
        // if validation fails. For gapselect, questionText contains [[n]] placeholders
        // which would be lost if we mutated early and then returned before rebuilding.
        var originalQuestionText = q.questionText;

        // Always save question text
        q.questionText = $('#edit-question-text').val().trim() || originalQuestionText || '';

        if (qtype === 'essay') {
            var newRubric = [];
            $('.aiquizmaker-edit-rubric-row').each(function() {
                var marks       = parseInt($(this).find('.aiquizmaker-edit-rubric-marks').val(), 10) || 1;
                var description = $(this).find('.aiquizmaker-edit-rubric-desc').val().trim();
                if (description) { newRubric.push({ marks: marks, description: description }); }
            });
            if (newRubric.length === 0) {
                showAlert('warning', 'Cannot Save', 'At least one marking criterion with a description is required.');
                return;
            }
            q.rubric     = newRubric;
            q.totalMarks = newRubric.reduce(function(sum, r) { return sum + r.marks; }, 0);

        } else if (qtype === 'multichoice') {
            var $radios    = $('input[name="edit-mc-correct"]');
            var $checked   = $radios.filter(':checked');
            var correctIdx = $radios.index($checked); // DOM position = correct choice index
            var newChoices = [];
            $('#edit-choices-container .aiquizmaker-edit-choice-row').each(function(ci) {
                newChoices.push({
                    text:      $(this).find('.aiquizmaker-edit-choice-text').val().trim(),
                    isCorrect: (ci === correctIdx),
                    feedback:  ''
                });
            });
            if (newChoices.length < 2) {
                showAlert('warning', 'Cannot Save', 'At least two answer choices are required.');
                return;
            }
            if (correctIdx < 0 || !newChoices.some(function(c) { return c.isCorrect; })) {
                showAlert('warning', 'Cannot Save', 'Please select a correct answer by clicking a radio button.');
                return;
            }
            q.choices     = newChoices;
            q.explanation = $('#edit-explanation').val().trim();
            q.totalMarks  = 1;

        } else if (qtype === 'truefalse') {
            var tfVal = $('input[name="edit-tf-answer"]:checked').val();
            if (!tfVal) {
                showAlert('warning', 'Cannot Save', 'Please select True or False as the correct answer.');
                return;
            }
            q.correctAnswer      = (tfVal === 'true');
            q.trueAnswerFeedback  = $('#edit-true-feedback').val().trim();
            q.falseAnswerFeedback = $('#edit-false-feedback').val().trim();
            q.explanation         = $('#edit-explanation').val().trim();
            q.totalMarks          = 1;

        } else if (qtype === 'matching') {
            var newPairs = [];
            $('#edit-pairs-container .aiquizmaker-edit-pair-row').each(function() {
                var sq = $(this).find('.aiquizmaker-edit-pair-subquestion').val().trim();
                var sa = $(this).find('.aiquizmaker-edit-pair-subanswer').val().trim();
                if (sq || sa) { newPairs.push({ subquestion: sq, subanswer: sa }); }
            });
            if (newPairs.length < 2) {
                showAlert('warning', 'Cannot Save', 'At least two match pairs are required.');
                return;
            }
            q.matchPairs  = newPairs;
            q.explanation = $('#edit-explanation').val().trim();
            q.totalMarks  = newPairs.length;

        } else if (qtype === 'gapselect') {
            var newGroups = [];
            $('#edit-gap-groups .aiquizmaker-edit-gap-group').each(function() {
                var opts = [];
                $(this).find('.aiquizmaker-edit-gap-opt-text').each(function() {
                    var v = $(this).val().trim();
                    if (v) { opts.push(v); }
                });
                if (opts.length > 0) { newGroups.push(opts); }
            });
            if (newGroups.length === 0) {
                // Restore questionText  -  mutation at the top of this function must be rolled back
                // when we return early, otherwise the [[n]] placeholders may be lost.
                q.questionText = originalQuestionText;
                showAlert('warning', 'Cannot Save', 'At least one gap group with options is required.');
                return;
            }
            // Validate placeholder count matches group count
            var placeholderCount = (q.questionText.match(/\[\[\d+\]\]/g) || []).length;
            if (placeholderCount !== newGroups.length) {
                // Restore questionText before returning to prevent partial state corruption.
                q.questionText = originalQuestionText;
                showAlert('warning', 'Placeholder Mismatch',
                    'The sentence contains ' + placeholderCount + ' placeholder(s) ([[1]], [[2]] etc.) but you have ' +
                    newGroups.length + ' gap group(s). Please make them match.');
                return;
            }
            q.selectOptions  = newGroups;
            q.generalFeedback = $('#edit-general-feedback').val().trim();
            q.totalMarks      = newGroups.length;

        } else if (qtype === 'shortanswer') {
            var newAnswers = [];
            $('#edit-answers-container .aiquizmaker-edit-answer-row').each(function() {
                var v = $(this).find('.aiquizmaker-edit-answer-text').val().trim();
                if (v) { newAnswers.push(v); }
            });
            if (newAnswers.length === 0) {
                showAlert('warning', 'Cannot Save', 'At least one accepted answer is required.');
                return;
            }
            q.blankSentence   = $('#edit-blank-sentence').val().trim();
            q.acceptedAnswers = newAnswers;
            q.explanation     = $('#edit-explanation').val().trim();
            q.totalMarks      = 1;
        }

        // Rebuild the question card and close modal
        var cardEl = $('#questions-list .aiquizmaker-question-card[data-question-idx="' + index + '"]');
        cardEl.replaceWith(buildQuestionCard(q, index));
        $('#edit-modal-overlay').remove();
        showAlert('success', 'Saved', 'Question updated. Click "Add to Quiz" or "Create in Moodle" to save to your LMS.');
    }

    /**
     * Regenerate a single question.
     * @param {number} index Question index.
     */
    function regenerateQuestion(index) {
        var q = lastGeneratedQuestions[index];
        if (!q) return;
        if (q.moodleQuestionType === 'description') return; // Section headings cannot be regenerated.

        var cardEl = $('#questions-list .aiquizmaker-question-card[data-question-idx="' + index + '"]');
        var btn = cardEl.find('.aiquizmaker-regenerate-btn');
        btn.prop('disabled', true).addClass('is-loading');

        // In ownquestions mode: regenerate using the full original question text via generatefromquestions.
        // In criteria mode: regenerate using the criteriaReference via the regenerate action.
        if (currentInputMode === 'ownquestions') {
            // Always use the frozen original question text so that every redo sends the same
            // source prompt to the AI  -  preventing state reuse from the first regenerated result.
            var questionText = q.originalQuestionText || q.questionText || '';
            if (!questionText) {
                btn.prop('disabled', false).removeClass('is-loading');
                showAlert('error', 'Error', 'Original question text not available for regeneration.');
                return;
            }

            var workplaceContextEnabled = isWorkplaceContextEnabled();
            var postData = {
                action: 'generatefromquestions',
                sesskey: config.sesskey,
                cmid: config.cmid || 0,
                questions: JSON.stringify([{text: questionText, modelAnswer: q.modelAnswer || ''}]),
                workplaceContextEnabled: workplaceContextEnabled ? '1' : '0',
                educationType: $('select[name="education_type"]').val() || 'vet',
                educationLevel: getEducationLevel(),
                extraInstructions: $('#aiquizmaker-extra-instructions').val() || '',
                language: config.language || '',
                moodleQuestionTypes: JSON.stringify([q.moodleQuestionType || 'essay']),
                selfMarkingStyles: JSON.stringify(getSelectedSelfMarkingStyles()),
                // v3.16.50: Accumulate ALL previously generated versions (not just the last)
                // to prevent the AI cycling between two outputs (A -> B -> A loop). Each regenerate
                // appends the outgoing questionText to q._generatedVersions; the next call then
                // sends the full history so the server can avoid every prior version.
                previousQuestionText: (q._generatedVersions || []).concat([q.questionText || '']).filter(Boolean).join('\n---PREV---\n').slice(0, 2000)
            };
            if (workplaceContextEnabled) {
                postData.country = $('select[name="country"]').val();
                postData.state = $('select[name="state"]').val();
                postData.industry = $('select[name="industry"]').val();
                postData.industryDetails = $('input[name="industry_details"]').val();
                postData.jobTitle = getJobTitle();
                postData.jobLevel = getSelectedJobLevels().join(', ');
            } else {
                postData.country = '';
                postData.state = '';
                postData.industry = '';
                postData.industryDetails = '';
                postData.jobTitle = '';
                postData.jobLevel = '';
            }

            $.ajax({
                url: config.ajaxurl,
                method: 'POST',
                data: postData,
                dataType: 'json',
                timeout: 120000
            }).done(function(response) {
                btn.prop('disabled', false).removeClass('is-loading');
                if (response.success && response.questions && response.questions.length > 0) {
                    var regenerated = response.questions[0];
                    // Re-attach the original model answer so future regenerations still use it.
                    regenerated.modelAnswer = q.modelAnswer || '';
                    // Freeze the original question text  -  every redo must use the same source
                    // prompt so the AI always generates from scratch, not from a prior result.
                    regenerated.originalQuestionText = q.originalQuestionText || questionText;
                    // Carry forward accumulated version history so the next redo avoids all prior outputs.
                    regenerated._generatedVersions = (q._generatedVersions || []).concat([q.questionText || '']).filter(Boolean);
                    lastGeneratedQuestions[index] = regenerated;
                    cardEl.replaceWith(buildQuestionCard(regenerated, index));
                    updateCredits(response.credits);
                    showAlert('success', 'Regenerated', 'Question ' + (index + 1) + ' has been regenerated.');
                } else {
                    showAlert('error', 'Error', response.error || 'Failed to regenerate question.');
                }
            }).fail(function() {
                btn.prop('disabled', false).removeClass('is-loading');
                showAlert('error', 'Error', 'Connection failed. Please try again.');
            });

        } else {
            // Criteria mode: use the original regenerate action.
            var formData = collectFormData();

            $.ajax({
                url: config.ajaxurl,
                method: 'POST',
                data: {
                    action: 'regenerate',
                    sesskey: config.sesskey,
                    criterionText: q.criteriaReference,
                    // v3.16.48: Send workplaceContextEnabled so PHP does not strip workplace
                    // context fields when the teacher has them configured. Previously omitted,
                    // causing all regenerated questions to ignore industry/job context.
                    workplaceContextEnabled: formData.workplaceContextEnabled ? '1' : '0',
                    country: formData.country,
                    state: formData.state,
                    industry: formData.industry,
                    industryDetails: formData.industryDetails,
                    jobTitle: formData.jobTitle,
                    jobLevel: formData.jobLevel,
                    educationType: formData.educationType,
                    educationLevel: formData.educationLevel,
                    'questionFormats[]': formData.questionFormats,
                    moodleQuestionTypes: JSON.stringify([q.moodleQuestionType || 'essay']),
                    selfMarkingStyles: JSON.stringify(getSelectedSelfMarkingStyles()),
                    extraInstructions: $('#aiquizmaker-extra-instructions').val() || '',
                    pastedContent: formData.pastedContent || '',
                    // v3.16.50: Send full version history to prevent A -> B -> A regenerate loop.
                    previousQuestionText: (q._generatedVersions || []).concat([q.questionText || '']).filter(Boolean).join('\n---PREV---\n').slice(0, 2000)
                },
                dataType: 'json',
                timeout: 120000
            }).done(function(response) {
                btn.prop('disabled', false).removeClass('is-loading');
                if (response.success && response.question) {
                    // v3.16.38: ALWAYS use the ORIGINAL criteriaReference  -  the AI may return the
                    // question text instead of the skill criterion, corrupting every subsequent redo.
                    // q.criteriaReference is the frozen original criterion; prefer it unconditionally.
                    response.question.criteriaReference = q.criteriaReference || response.question.criteriaReference || '';
                    // v3.16.50: Carry forward version history so all prior texts are avoided next time.
                    response.question._generatedVersions = (q._generatedVersions || []).concat([q.questionText || '']).filter(Boolean);
                    lastGeneratedQuestions[index] = response.question;
                    cardEl.replaceWith(buildQuestionCard(response.question, index));
                    // v3.16.48: Refresh credit display after criteria-mode regeneration so the
                    // credit counter updates without waiting for the next full-page reload.
                    fetchCredits();
                    showAlert('success', 'Regenerated', 'Question ' + (index + 1) + ' has been regenerated.');
                } else {
                    showAlert('error', 'Error', response.error || 'Failed to regenerate question.');
                }
            }).fail(function() {
                btn.prop('disabled', false).removeClass('is-loading');
                showAlert('error', 'Error', 'Connection failed. Please try again.');
            });
        }
    }

    /**
     * Copy XML to clipboard.
     */
    function copyXmlToClipboard() {
        var xmlEl = $('#moodle-xml');
        xmlEl.select();
        document.execCommand('copy');

        var btn = $('#copy-xml-btn');
        var originalHtml = btn.html();
        btn.html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> ' + strings.copied);

        setTimeout(function() {
            btn.html(originalHtml);
        }, 2000);
    }

    /**
     * Load saved extra instructions from server.
     */
    function loadExtraInstructions() {
        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'getsettings',
                sesskey: config.sesskey
            },
            dataType: 'json',
            timeout: 10000
        }).done(function(response) {
            if (response.ok && response.settings && response.settings.extraInstructions) {
                $('#aiquizmaker-extra-instructions').val(response.settings.extraInstructions);
                log('Loaded saved extra instructions');
            }
        }).fail(function() {
            log('Failed to load extra instructions (may not exist yet)');
        });
    }

    /**
     * Save extra instructions to server.
     */
    function saveExtraInstructions() {
        var extraInstructions = $('#aiquizmaker-extra-instructions').val() || '';
        var statusEl = $('#aiquizmaker-instructions-status');
        var btn = $('#aiquizmaker-save-instructions');

        btn.prop('disabled', true);
        statusEl.removeClass('is-visible is-error');

        $.ajax({
            url: config.ajaxurl,
            method: 'POST',
            data: {
                action: 'savesettings',
                sesskey: config.sesskey,
                extraInstructions: extraInstructions
            },
            dataType: 'json',
            timeout: 10000
        }).done(function(response) {
            btn.prop('disabled', false);
            if (response.ok) {
                statusEl.text('Saved').removeClass('is-error').addClass('is-visible');
                log('Extra instructions saved');
            } else {
                statusEl.text('Failed to save').addClass('is-error is-visible');
                log('Failed to save extra instructions:', response.error);
            }
            setTimeout(function() {
                statusEl.removeClass('is-visible');
            }, 3000);
        }).fail(function() {
            btn.prop('disabled', false);
            statusEl.text('Failed to save').addClass('is-error is-visible');
            setTimeout(function() {
                statusEl.removeClass('is-visible');
            }, 3000);
        });
    }

    /**
     * Load all required language strings.
     * @return {Promise} Promise that resolves when strings are loaded.
     */
    function loadStrings() {
        return Str.get_strings([
            {key: 'processing', component: 'local_aiquizmaker'},
            {key: 'generating', component: 'local_aiquizmaker'},
            {key: 'error_connection', component: 'local_aiquizmaker'},
            {key: 'error_fetching_credits', component: 'local_aiquizmaker'},
            {key: 'credits_error', component: 'local_aiquizmaker'},
            {key: 'select_industry', component: 'local_aiquizmaker'},
            {key: 'criteria_placeholder', component: 'local_aiquizmaker'},
            {key: 'question_singular', component: 'local_aiquizmaker'},
            {key: 'question_plural', component: 'local_aiquizmaker'},
            {key: 'error_missing_criteria', component: 'local_aiquizmaker'},
            {key: 'error_missing_criteria_message', component: 'local_aiquizmaker'},
            {key: 'error_missing_fields', component: 'local_aiquizmaker'},
            {key: 'error_missing_fields_message', component: 'local_aiquizmaker'},
            {key: 'success_generated', component: 'local_aiquizmaker'},
            {key: 'success_generated_message', component: 'local_aiquizmaker'},
            {key: 'insufficient_credits', component: 'local_aiquizmaker'},
            {key: 'insufficient_credits_message', component: 'local_aiquizmaker'},
            {key: 'buy_credits', component: 'local_aiquizmaker'},
            {key: 'error_generation', component: 'local_aiquizmaker'},
            {key: 'question', component: 'local_aiquizmaker'},
            {key: 'marks', component: 'local_aiquizmaker'},
            {key: 'marking_rubric', component: 'local_aiquizmaker'},
            {key: 'one_mark', component: 'local_aiquizmaker'},
            {key: 'rubric_hazard', component: 'local_aiquizmaker'},
            {key: 'rubric_example', component: 'local_aiquizmaker'},
            {key: 'rubric_control', component: 'local_aiquizmaker'},
            {key: 'criteria', component: 'local_aiquizmaker'},
            {key: 'copied', component: 'local_aiquizmaker'},
            {key: 'error', component: 'local_aiquizmaker'},
            {key: 'select_state', component: 'local_aiquizmaker'},
            {key: 'bulk_add_success', component: 'local_aiquizmaker'},
            {key: 'bulk_add_empty', component: 'local_aiquizmaker'},
            {key: 'select_level', component: 'local_aiquizmaker'},
            {key: 'select_job_title', component: 'local_aiquizmaker'},
            {key: 'job_title_other', component: 'local_aiquizmaker'},
            {key: 'marks_label', component: 'local_aiquizmaker'},
            {key: 'mark_singular', component: 'local_aiquizmaker'},
            {key: 'apply_to_all_success', component: 'local_aiquizmaker'},
            {key: 'extract_criteria_loading', component: 'local_aiquizmaker'},
            {key: 'extract_criteria_none', component: 'local_aiquizmaker'},
            {key: 'extract_criteria_error', component: 'local_aiquizmaker'},
            {key: 'ownquestions_btn', component: 'local_aiquizmaker'},
            {key: 'ownquestions_empty_error', component: 'local_aiquizmaker'},
            {key: 'ownquestions_generating', component: 'local_aiquizmaker'},
            {key: 'generate', component: 'local_aiquizmaker'},
            {key: 'chatgpt_topic_required', component: 'local_aiquizmaker'},
            {key: 'chatgpt_copied', component: 'local_aiquizmaker'}
        ]).then(function(results) {
            strings.processing = results[0];
            strings.generating = results[1];
            strings.error_connection = results[2];
            strings.error_fetching_credits = results[3];
            strings.credits_error = results[4];
            strings.select_industry = results[5];
            strings.criteria_placeholder = results[6];
            strings.question_singular = results[7];
            strings.question_plural = results[8];
            strings.error_missing_criteria = results[9];
            strings.error_missing_criteria_message = results[10];
            strings.error_missing_fields = results[11];
            strings.error_missing_fields_message = results[12];
            strings.success_generated = results[13];
            strings.success_generated_message = results[14];
            strings.insufficient_credits = results[15];
            strings.insufficient_credits_message = results[16];
            strings.buy_credits = results[17];
            strings.error_generation = results[18];
            strings.question = results[19];
            strings.marks = results[20];
            strings.marking_rubric = results[21];
            strings.one_mark = results[22];
            strings.rubric_hazard = results[23];
            strings.rubric_example = results[24];
            strings.rubric_control = results[25];
            strings.criteria = results[26];
            strings.copied = results[27];
            strings.error = results[28];
            strings.select_state = results[29];
            strings.bulk_add_success = results[30];
            strings.bulk_add_empty = results[31];
            strings.select_level = results[32];
            strings.select_job_title = results[33];
            strings.job_title_other = results[34];
            strings.marks_label = results[35];
            strings.mark_singular = results[36];
            strings.apply_to_all_success = results[37];
            strings.extract_criteria_loading = results[38];
            strings.extract_criteria_none = results[39];
            strings.extract_criteria_error = results[40];
            strings.ownquestions_btn = results[41];
            strings.ownquestions_empty_error = results[42];
            strings.ownquestions_generating = results[43];
            strings.generate_btn = results[44];
            strings.chatgpt_topic_required = results[45];
            strings.chatgpt_copied = results[46];
            return true;
        });
    }

    return {
        /**
         * Initialize the AI Quiz Maker module.
         * @param {Object} cfg Configuration object.
         */
        init: function(cfg) {
            config = cfg;
            log('Initializing with config:', config);

            loadStrings().then(function() {
                log('Strings loaded');
                fetchCredits();
                fetchIndustries();
                fetchCategories();

                // Initialize state dropdown based on current country selection
                var initialCountry = $('#country-select').val();
                if (initialCountry) {
                    updateStateDropdown(initialCountry);
                }

                // Update states when country changes
                $(document).on('change', '#country-select', function() {
                    var country = $(this).val();
                    updateStateDropdown(country);
                });

                // Update job level checkboxes AND job title panel when industry changes.
                $(document).on('change', '#industry-select', function() {
                    var industry = $(this).val();
                    updateJobLevelCheckboxes(industry);
                    // Show all titles for this industry (no level filter yet).
                    updateJobTitlePanel(industry, []);
                });

                // Update job title panel when any job level checkbox changes.
                $(document).on('change', 'input[name="job_level[]"]', function() {
                    var industry = $('#industry-select').val();
                    var selectedLevels = getSelectedJobLevels();
                    updateJobTitlePanel(industry, selectedLevels);
                });

                // Filter job title checkboxes as user types in search box.
                $(document).on('input', '#job-title-search', function() {
                    filterJobTitles($(this).val());
                });

                // Select All visible job title checkboxes.
                $(document).on('click', '#job-title-select-all', function() {
                    $('#job-title-checkboxes .aiquizmaker-role-item:visible input[type="checkbox"]').prop('checked', true);
                });

                // Clear All job title checkboxes.
                $(document).on('click', '#job-title-clear-all', function() {
                    $('#job-title-checkboxes input[name="job_title[]"]').prop('checked', false);
                });

                // Handle education type switching
                $(document).on('change', '#education-type-select', function() {
                    var educationType = $(this).val();
                    if (educationType === 'academic') {
                        // Show academic fields, hide VET fields
                        $('#academic-level-field').show();
                        $('#vet-level-field').hide();
                        $('#academic-info-card').show();
                        $('#vet-info-card').hide();
                    } else {
                        // Show VET fields, hide academic fields
                        $('#vet-level-field').show();
                        $('#academic-level-field').hide();
                        $('#vet-info-card').show();
                        $('#academic-info-card').hide();
                    }
                });

                // Bulk add criteria button
                $(document).on('click', '#bulk-add-btn', function() {
                    bulkAddCriteria();
                });

                // Apply to All button - set question count for all criteria
                $(document).on('click', '#apply-all-btn', function() {
                    applyCountToAll();
                });

                // Auto-cleanup pasted text in bulk criteria textarea
                $(document).on('paste', '#bulk-criteria-input', function(e) {
                    var textarea = $(this);
                    // Use setTimeout to let the paste complete first
                    setTimeout(function() {
                        var cleaned = cleanupPastedText(textarea.val());
                        textarea.val(cleaned);
                    }, 0);
                });

                // Extract Criteria from pasted content button
                $(document).on('click', '#extract-criteria-btn', function() {
                    extractCriteriaFromContent();
                });

                // Clear pasted content button
                $(document).on('click', '#clear-content-btn', function() {
                    $('#pasted-content-input').val('').trigger('focus');
                });

                // ChatGPT Prompt Helper  -  toggle expand/collapse.
                $(document).on('click keypress', '#prompt-helper-toggle', function(e) {
                    if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return;
                    var body = $('#prompt-helper-body');
                    var chevron = $('#prompt-helper-chevron');
                    var toggle = $(this);
                    var isOpen = body.is(':visible');
                    body.slideToggle(200);
                    chevron.css('transform', isOpen ? '' : 'rotate(180deg)');
                    toggle.attr('aria-expanded', isOpen ? 'false' : 'true');
                });

                // ChatGPT Prompt Helper  -  generate the prompt.
                $(document).on('click', '#prompt-helper-generate-btn', function() {
                    var topic = $.trim($('#prompt-helper-topic').val());
                    if (!topic) {
                        $('#prompt-helper-topic').focus();
                        alert(strings.chatgpt_topic_required || 'Please enter a topic before generating the prompt.');
                        return;
                    }
                    var count = parseInt($('#prompt-helper-count').val(), 10) || 10;
                    var level = $('#prompt-helper-level').val() || '';
                    var includeModelResponses = $('#prompt-helper-modelresponses').is(':checked');
                    var includeHeadings = $('#prompt-helper-headings').is(':checked');
                    var selectedTypes = getMoodleQuestionTypes();
                    var prompt = buildChatGPTPrompt(topic, count, level, includeModelResponses, includeHeadings, selectedTypes);
                    $('#prompt-helper-result').val(prompt);
                    $('#prompt-helper-output').show();
                    $('#prompt-helper-copied-msg').hide().text('');
                });

                // ChatGPT Prompt Helper  -  copy to clipboard.
                $(document).on('click', '#prompt-helper-copy-btn', function() {
                    var text = $('#prompt-helper-result').val();
                    if (!text) return;
                    var msg = $('#prompt-helper-copied-msg');
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            msg.text(strings.chatgpt_copied || 'Copied!').show();
                            setTimeout(function() { msg.fadeOut(); }, 2500);
                        });
                    } else {
                        // Fallback for older browsers.
                        var ta = document.getElementById('prompt-helper-result');
                        ta.select();
                        try {
                            document.execCommand('copy');
                            msg.text(strings.chatgpt_copied || 'Copied!').show();
                            setTimeout(function() { msg.fadeOut(); }, 2500);
                        } catch (err) { /* silent */ }
                    }
                });

                $(document).on('click', '#add-criteria-btn', function() {
                    addCriteriaRow();
                });

                $(document).on('click', '.aiquizmaker-remove-criteria', function() {
                    removeCriteriaRow($(this).closest('.aiquizmaker-criteria-row'));
                });

                $(document).on('submit', '#aiquizmaker-form', function(e) {
                    e.preventDefault();
                    if (currentInputMode === 'ownquestions') {
                        generateFromOwnQuestions();
                    } else {
                        generateQuestions();
                    }
                });

                // Input mode tab switching.
                $(document).on('click', '.aiquizmaker-mode-tab', function() {
                    var mode = $(this).data('mode');
                    if (mode) {
                        switchInputMode(mode);
                    }
                });

                $(document).on('click', '#copy-xml-btn', function() {
                    copyXmlToClipboard();
                });

                // Download XML button
                $(document).on('click', '#download-xml-btn', function() {
                    downloadXml();
                });

                // Download Excel mapping buttons (both in Excel section and inline in success message)
                $(document).on('click', '#download-excel-btn, #download-excel-inline-btn', function() {
                    downloadExcel();
                });

                // Create questions in Moodle button
                $(document).on('click', '#create-questions-btn', function() {
                    var categoryId = $('#question-category-select').val();
                    createQuestionsInMoodle(categoryId);
                });

                // Add to quiz button (for quiz context)
                $(document).on('click', '#add-to-quiz-btn', function() {
                    addQuestionsToQuiz();
                });

                // Fix 1: Individual question selection checkbox  ->  update button label
                $(document).on('change', '.aiquizmaker-question-checkbox', function() {
                    updateAddToQuizButtonLabel();
                });

                // Fix 1: Select all / none toggle
                $(document).on('change', '#aiquizmaker-select-all-chk', function() {
                    var checked = $(this).is(':checked');
                    $('.aiquizmaker-question-checkbox').prop('checked', checked);
                    updateAddToQuizButtonLabel();
                });

                // Edit question button
                $(document).on('click', '.aiquizmaker-edit-question-btn', function() {
                    var idx = parseInt($(this).data('idx'), 10);
                    openEditModal(idx);
                });

                // Regenerate question button
                $(document).on('click', '.aiquizmaker-regenerate-btn', function() {
                    var idx = parseInt($(this).data('idx'), 10);
                    regenerateQuestion(idx);
                });

                // Workplace context toggle - show/hide fields
                $(document).on('change', '#workplace-context-toggle', function() {
                    var isChecked = $(this).is(':checked');
                    var fieldsContainer = $('#workplace-context-fields');
                    if (isChecked) {
                        fieldsContainer.slideDown(200);
                    } else {
                        fieldsContainer.slideUp(200);
                    }
                    log('Workplace context toggled:', isChecked);
                });

                // Question type checkboxes  -  show/hide format sections when types change
                $(document).on('change', '.aiquizmaker-qtype-check', function() {
                    updateFormatSectionsVisibility();
                });

                // Extra AI Instructions section toggle
                $(document).on('click', '#aiquizmaker-instructions-toggle', function() {
                    var section = $(this).closest('.aiquizmaker-instructions-section');
                    section.toggleClass('is-collapsed');
                });

                // Save extra instructions button
                $(document).on('click', '#aiquizmaker-save-instructions', function() {
                    saveExtraInstructions();
                });

                // Load saved extra instructions on page load
                loadExtraInstructions();

                // Set initial visibility of format sections based on default question type selection
                updateFormatSectionsVisibility();

                log('Initialization complete');
            }).catch(function(error) {
                Notification.exception(error);
            });
        }
    };
});
