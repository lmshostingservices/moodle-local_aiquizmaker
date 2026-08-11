/**
 * AI Quiz Maker Quiz Button Integration
 *
 * Adds an "AI Quiz Maker" button next to the "Add question" button
 * on the Moodle quiz edit page.
 *
 * @module     local_aiquizmaker/quizbutton
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    'use strict';

    var config = {};

    /**
     * Find and inject the AI Quiz Maker button next to the Add question button.
     */
    function injectButton() {
        // Don't inject twice
        if ($('#aiquizmaker-quiz-button').length > 0) {
            return true;
        }

        var targetFound = false;
        var essayMakerBtn = createButton();

        // Strategy 1: Find the quiz "Add" dropdown menu (specific to quiz edit page)
        var addMenuBtn = $('[data-action="add"], .page-add-actions .toggle-display').first();
        if (addMenuBtn.length) {
            // Insert after the parent action menu container, not inside the dropdown
            var actionMenu = addMenuBtn.closest('.action-menu');
            if (actionMenu.length) {
                actionMenu.after(essayMakerBtn);
                console.log('[EssayMaker] Button injected after Add action menu');
                return true;
            }
        }

        // Strategy 2: Find "Add" or "Add question" link/button
        var addBtn = $('a:contains("Add question"), a.addquestion, [data-action="addquestion"]').first();
        if (addBtn.length) {
            addBtn.closest('.action-menu, .dropdown').after(essayMakerBtn);
            console.log('[EssayMaker] Button injected next to Add question link');
            return true;
        }

        // Strategy 3: Find the question bank link/button
        var qbankBtn = $('a[href*="questionbank"], button[data-action="questionbank"]').first();
        if (qbankBtn.length) {
            qbankBtn.after(essayMakerBtn);
            targetFound = true;
            console.log('[EssayMaker] Button injected next to question bank');
            return true;
        }

        // Strategy 4: Moodle 4 quiz edit structure
        var actionBar = $('.mod_quiz-edit-content .d-flex.justify-content-between, .mod_quiz-edit-content .btn-group').first();
        if (actionBar.length) {
            actionBar.append(essayMakerBtn);
            targetFound = true;
            console.log('[EssayMaker] Button injected into action bar');
            return true;
        }

        // Strategy 5: Find the secondary navigation (Moodle 4+)
        var secondaryNav = $('.secondary-navigation .nav, .secondary-navigation-content, #page-navbar .nav').first();
        if (secondaryNav.length) {
            var navItem = $('<li class="nav-item">').append(essayMakerBtn.removeClass('ms-2 ml-2'));
            secondaryNav.append(navItem);
            targetFound = true;
            console.log('[EssayMaker] Button injected into secondary nav');
            return true;
        }

        // Strategy 6: Activity header actions (Moodle 4+)
        var activityHeader = $('.activity-header .activity-actions, .activityinstance .actions, #region-main .action-menu').first();
        if (activityHeader.length) {
            activityHeader.append(essayMakerBtn);
            targetFound = true;
            console.log('[EssayMaker] Button injected into activity header');
            return true;
        }

        // Strategy 7: Any button group on the page
        var btnGroup = $('#region-main .btn-group, #region-main .d-flex.gap-2').first();
        if (btnGroup.length) {
            btnGroup.append(essayMakerBtn);
            targetFound = true;
            console.log('[EssayMaker] Button injected into button group');
            return true;
        }

        // Strategy 8: Last resort - add to the page header area
        var pageHeader = $('#page-header .card-body, #page-header .d-flex, .page-header-headings').first();
        if (pageHeader.length) {
            pageHeader.append(essayMakerBtn);
            targetFound = true;
            console.log('[EssayMaker] Button injected into page header');
            return true;
        }

        console.log('[EssayMaker] Could not find suitable injection point');
        return false;
    }

    /**
     * Create the AI Quiz Maker button element.
     * @return {jQuery} The button element.
     */
    function createButton() {
        var btn = $('<a>')
            .attr('id', 'aiquizmaker-quiz-button')
            .attr('href', config.essayMakerUrl + (config.cmid ? '?cmid=' + config.cmid : ''))
            .addClass('btn btn-secondary ms-2 ml-2')
            .css({
                'background-color': '#f97316',
                'border-color': '#f97316',
                'color': '#ffffff',
                'font-weight': '500',
                'margin-left': '8px'
            })
            .html('<i class="fa fa-magic mr-1" style="margin-right: 4px;"></i>' + config.buttonText);

        // Add hover effect
        btn.on('mouseenter', function() {
            $(this).css({
                'background-color': '#ea580c',
                'border-color': '#ea580c'
            });
        }).on('mouseleave', function() {
            $(this).css({
                'background-color': '#f97316',
                'border-color': '#f97316'
            });
        });

        return btn;
    }

    /**
     * Initialize the quiz button integration.
     * Moodle AMD passes associative array as a single object parameter.
     * @param {Object} params Configuration parameters from PHP.
     */
    function init(params) {
        // Moodle passes PHP associative array as object
        config = {
            cmid: params.cmid || 0,
            buttonText: params.buttonText || 'AI Quiz',
            essayMakerUrl: params.essayMakerUrl || '/local/aiquizmaker/index.php'
        };
        
        console.log('[EssayMaker] Init with config:', config);

        // Try to inject the button immediately
        $(document).ready(function() {
            var injected = injectButton();

            // If not found immediately, use a MutationObserver to wait for the DOM
            if (!injected) {
                // Try again after a short delay (Moodle may load content dynamically)
                setTimeout(function() {
                    injected = injectButton();
                    
                    if (!injected) {
                        // Set up a mutation observer to watch for the button area
                        var observer = new MutationObserver(function(mutations) {
                            if (injectButton()) {
                                observer.disconnect();
                            }
                        });

                        observer.observe(document.body, {
                            childList: true,
                            subtree: true
                        });

                        // Stop observing after 10 seconds to avoid memory leaks
                        setTimeout(function() {
                            observer.disconnect();
                        }, 10000);
                    }
                }, 500);
            }
        });
    }

    return {
        init: init
    };
});
