// Workforce Analytics Dashboard JavaScript

// Organizational Composition Data with Stories
const organizationalComposition = [
    { 
        classification: 'Rank-and-File', 
        count: 237, 
        color: 'from-blue-500 to-blue-400',
        role: 'Frontline operational staff who execute daily tasks including meter reading, customer service, maintenance, and water distribution operations.',
        importance: 'Critical - They are the operational backbone, directly interacting with customers and maintaining infrastructure. Their performance directly impacts service quality and customer satisfaction.',
        story: {
            title: '👷 Rank-and-File: The Operational Backbone',
            text: 'With 237 employees (68% of our workforce), Rank-and-File staff are the heartbeat of daily operations. They handle frontline tasks from meter reading to customer service, ensuring water reaches every household. Their dedication and hands-on expertise keep the system running 24/7. Investing in their training and well-being directly impacts service quality.'
        }
    },
    { 
        classification: 'Supervisors', 
        count: 50, 
        color: 'from-purple-500 to-purple-400',
        role: 'Middle management who coordinate teams, translate strategic directives into actionable tasks, mentor employees, and ensure quality standards are met.',
        importance: 'High - They bridge the gap between management and staff, ensuring effective communication and execution. They maintain quality control and develop team capabilities.',
        story: {
            title: '📋 Supervisors: The Bridge Builders',
            text: '50 Supervisors (14% of workforce) serve as the critical link between strategy and execution. They translate management directives into actionable tasks, mentor rank-and-file employees, and ensure quality standards are met. A 1:4.7 supervisor-to-staff ratio indicates healthy oversight while allowing autonomy for skilled workers.'
        }
    },
    { 
        classification: 'Division Managers', 
        count: 27, 
        color: 'from-indigo-500 to-indigo-400',
        role: 'Tactical leaders who manage specialized units within departments, oversee budgets, allocate resources, and drive performance metrics.',
        importance: 'Very High - They translate department strategy into operational plans, manage resources efficiently, and ensure teams meet performance targets. Their decisions impact operational efficiency.',
        story: {
            title: '🎯 Division Managers: The Tactical Leaders',
            text: '27 Division Managers (8% of workforce) coordinate specialized units within departments. They manage budgets, allocate resources, and drive performance metrics. Each oversees an average of 9 employees, balancing hands-on involvement with strategic thinking. Their decisions directly impact operational efficiency and team morale.'
        }
    },
    { 
        classification: 'Dept. Managers', 
        count: 13, 
        color: 'from-teal-500 to-teal-400',
        role: 'Strategic leaders who manage entire functional departments, set departmental goals, manage cross-functional projects, and develop future leaders.',
        importance: 'Critical - They align departmental operations with organizational strategy, manage complex projects, and develop leadership pipelines. Their experience provides institutional knowledge.',
        story: {
            title: '🏢 Department Managers: The Strategic Architects',
            text: '13 Department Managers (4% of workforce) lead entire functional areas—from Finance to Operations. They set departmental goals aligned with organizational strategy, manage cross-functional projects, and develop future leaders. Their experience (avg. 15+ years) provides institutional knowledge critical for long-term planning.'
        }
    },
    { 
        classification: 'General Manager', 
        count: 2, 
        color: 'from-amber-500 to-amber-400',
        role: 'Executive leadership responsible for setting organizational vision, approving major investments, strategic planning, and representing the company to stakeholders.',
        importance: 'Essential - They provide strategic direction, make critical organizational decisions, and ensure accountability. Their leadership shapes the organization\'s future and stakeholder relationships.',
        story: {
            title: '⭐ General Manager: The Executive Core',
            text: '2 General Managers (<1% of workforce) form the executive leadership team. They set organizational vision, approve major investments, and represent the company to stakeholders. This lean executive structure ensures agile decision-making while maintaining clear accountability for the organization\'s 348 employees.'
        }
    },
    { 
        classification: 'Directors', 
        count: 19, 
        color: 'from-rose-500 to-rose-400',
        role: 'Governance leaders who provide oversight, ensure regulatory compliance, approve policies, and safeguard organizational integrity through strategic guidance.',
        importance: 'Essential - They ensure compliance, maintain governance standards, and provide diverse expertise for critical decisions. They protect organizational integrity and long-term sustainability.',
        story: {
            title: '🎖️ Directors: The Governance Stewards',
            text: '19 Directors (5% of workforce) provide governance oversight and strategic guidance. They ensure regulatory compliance, approve policies, and safeguard organizational integrity. Their diverse expertise—from engineering to finance—brings multidisciplinary perspectives to critical decisions affecting water service delivery.'
        }
    }
];

let currentCardView = 'age';
let currentModalDept = null;
let globalSummary = null;

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', () => {
    initializeHeroSection();
    renderDepartmentCards();
    renderOrgCompositionChart();
    addChartInteractivity();
    addScrollAnimations();
    initializeModal();
    initializeCardViewToggles();
    updateDepartmentBreakdownNarrative(currentCardView);
    initializeDepartmentBreakdownScrollAnimations();
    initializeGuideNarratives();
    initializeWorkforceSlideshow();

    // Global summary section ("By the Numbers")
    globalSummary = computeGlobalSummary();
    renderGlobalSummary(globalSummary);
    updateLastUpdatedTimestamp();
});

// Data Storytelling Guide narratives (clickable mini-cards)
// Store narratives globally for editing
let guideNarratives = {
    pipeline: {
        title: 'Pipeline + Risk — Workforce Age Distribution & Continuity',
        paragraphs: [
            'The current age profile indicates a concentration of experienced employees in the 55+ segment, creating potential exposure to retirement-driven knowledge loss over the next 3–5 years. While this experience base is a strength today, it presents a medium-term risk if not actively managed.',
            'The 25–44 segment shows moderate growth, suggesting early progress in pipeline development, but not yet at a scale sufficient to fully offset upcoming attrition. Without intervention, this imbalance could affect leadership continuity, institutional memory, and delivery capacity.',
            'What this means for leaders:',
            '• Near-term stability, but growing succession risk\n• Knowledge transfer windows are narrowing\n• Workforce planning must shift from reactive to proactive',
            'Recommended actions:',
            '• Prioritize succession planning for critical roles\n• Accelerate mid-career hiring and internal promotions\n• Implement structured mentoring and knowledge-transfer programs'
        ]
    },
    diversity: {
        title: 'Diversity + Balance — Gender Representation Snapshot',
        paragraphs: [
            'The gender distribution reveals a significant imbalance, with male representation at 68% and female representation at 29%, indicating a gap of over 30%. This imbalance is most pronounced in roles with higher decision-making responsibility, which may limit diversity of perspectives at leadership and operational levels.',
            'While some departments approach parity, the overall snapshot highlights the need for focused, role-specific interventions rather than broad, one-size-fits-all initiatives. Improving balance is not only an inclusion goal, but a driver of stronger decision quality, innovation, and employee engagement.',
            'What this means for leaders:',
            '• Representation gaps are structural, not incidental\n• Leadership pipelines may be reinforcing imbalance\n• Diversity efforts need clearer accountability',
            'Recommended actions:',
            '• Target gender balance in hiring for priority roles\n• Ensure diverse representation in shortlists and panels\n• Develop partnerships and programs to expand the talent pool'
        ]
    },
    engagement: {
        title: 'Engagement + Stability — Employment Mix & Workforce Continuity',
        paragraphs: [
            'The workforce composition shows a strong core of regular employees (70%), providing stability and institutional knowledge. Contract roles (20%) and JO engagements (10%) add flexibility, but also introduce higher turnover risk in key operational areas.',
            'While this mix supports cost and capacity management, over-reliance on non-regular roles in critical functions can affect continuity, onboarding efficiency, and long-term capability development. Aligning workforce mix with strategic priorities is essential to sustaining performance.',
            'What this means for leaders:',
            '• Strong baseline stability with targeted flexibility\n• Risk of knowledge loss in high-turnover roles\n• Budget decisions directly influence engagement outcomes',
            'Recommended actions:',
            '• Review critical roles held by non-regular staff\n• Convert high-impact contract roles where feasible\n• Strengthen onboarding and knowledge retention practices'
        ]
    }
};

let currentGuideKey = null;
let editMode = {
    pipeline: false,
    diversity: false,
    engagement: false,
    narrative: false
};

function initializeGuideNarratives() {
    const box = document.getElementById('guideNarrativeBox');
    const titleEl = document.getElementById('guideNarrativeTitle');
    const bodyEl = document.getElementById('guideNarrativeBody');
    if (!box || !titleEl || !bodyEl) return;

    function renderDefaultNarrative() {
        titleEl.textContent = 'Click a card above to see the narrative.';
        bodyEl.innerHTML = '';
        const p = document.createElement('p');
        p.textContent =
            'Use Pipeline + Risk, Diversity + Balance, and Engagement + Stability as ready-made story frames.';
        bodyEl.appendChild(p);
        document.getElementById('editNarrativeBtn').classList.add('hidden');
    }

    function renderGuideNarrative(key) {
        // Toggle off if clicking the same card again
        if (currentGuideKey === key && !editMode.narrative) {
            currentGuideKey = null;
            renderDefaultNarrative();
            return;
        }

        const data = guideNarratives[key];
        if (!data) return;

        currentGuideKey = key;
        titleEl.textContent = data.title;
        bodyEl.innerHTML = '';

        data.paragraphs.forEach((para) => {
            const p = document.createElement('p');
            p.className = 'whitespace-pre-line';
            p.textContent = para;
            bodyEl.appendChild(p);
        });

        // Show edit button when narrative is displayed
        document.getElementById('editNarrativeBtn').classList.remove('hidden');

        // Simple highlight animation
        box.classList.add('ring-2', 'ring-blue-300');
        setTimeout(() => box.classList.remove('ring-2', 'ring-blue-300'), 300);
        
        // Make editable if already in edit mode
        if (editMode.narrative) {
            makeNarrativeEditable();
        }
    }

    const pipelineCard = document.getElementById('guidePipelineCard');
    const diversityCard = document.getElementById('guideDiversityCard');
    const engagementCard = document.getElementById('guideEngagementCard');

    if (pipelineCard) {
        pipelineCard.addEventListener('click', (e) => {
            // Don't trigger if clicking the edit button
            if (!e.target.closest('button')) {
                renderGuideNarrative('pipeline');
            }
        });
    }
    if (diversityCard) {
        diversityCard.addEventListener('click', (e) => {
            // Don't trigger if clicking the edit button
            if (!e.target.closest('button')) {
                renderGuideNarrative('diversity');
            }
        });
    }
    if (engagementCard) {
        engagementCard.addEventListener('click', (e) => {
            // Don't trigger if clicking the edit button
            if (!e.target.closest('button')) {
                renderGuideNarrative('engagement');
            }
        });
    }

    // Ensure default text is shown on load
    renderDefaultNarrative();
}

// Global function to toggle edit mode
function toggleEditMode(type) {
    if (type === 'narrative') {
        editMode.narrative = !editMode.narrative;
        if (editMode.narrative) {
            makeNarrativeEditable();
        } else {
            saveNarrativeContent();
            makeNarrativeReadonly();
        }
    } else {
        editMode[type] = !editMode[type];
        if (editMode[type]) {
            makeCardEditable(type);
        } else {
            saveCardContent(type);
            makeCardReadonly(type);
        }
    }
}

function makeCardEditable(type) {
    const subtitleEl = document.getElementById(type + 'Subtitle');
    const percentagesEl = document.getElementById(type + 'Percentages');
    const bulletsEl = document.getElementById(type + 'Bullets');
    
    if (subtitleEl) {
        subtitleEl.contentEditable = 'true';
        subtitleEl.classList.add('editable-active');
    }
    if (percentagesEl) {
        percentagesEl.contentEditable = 'true';
        percentagesEl.classList.add('editable-active');
    }
    if (bulletsEl) {
        bulletsEl.contentEditable = 'true';
        bulletsEl.classList.add('editable-active');
        // Make individual paragraphs editable
        Array.from(bulletsEl.querySelectorAll('p')).forEach(p => {
            p.contentEditable = 'true';
        });
    }
    
    // Change edit button to save button
    const editBtn = document.getElementById('edit' + type.charAt(0).toUpperCase() + type.slice(1) + 'Btn');
    if (editBtn) {
        editBtn.innerHTML = '<svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        editBtn.title = 'Save changes';
        editBtn.classList.add('bg-green-50', 'hover:bg-green-100');
    }
}

function makeCardReadonly(type) {
    const subtitleEl = document.getElementById(type + 'Subtitle');
    const percentagesEl = document.getElementById(type + 'Percentages');
    const bulletsEl = document.getElementById(type + 'Bullets');
    
    if (subtitleEl) {
        subtitleEl.contentEditable = 'false';
        subtitleEl.classList.remove('editable-active');
    }
    if (percentagesEl) {
        percentagesEl.contentEditable = 'false';
        percentagesEl.classList.remove('editable-active');
    }
    if (bulletsEl) {
        bulletsEl.contentEditable = 'false';
        bulletsEl.classList.remove('editable-active');
        Array.from(bulletsEl.querySelectorAll('p')).forEach(p => {
            p.contentEditable = 'false';
        });
    }
    
    // Change save button back to edit button
    const editBtn = document.getElementById('edit' + type.charAt(0).toUpperCase() + type.slice(1) + 'Btn');
    if (editBtn) {
        editBtn.innerHTML = '<svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
        editBtn.title = 'Edit content';
        editBtn.classList.remove('bg-green-50', 'hover:bg-green-100');
    }
}

function saveCardContent(type) {
    // Content is automatically saved when contentEditable is used
    // No need to do anything special as the DOM is already updated
}

function makeNarrativeEditable() {
    const titleEl = document.getElementById('guideNarrativeTitle');
    const bodyEl = document.getElementById('guideNarrativeBody');
    
    if (titleEl) {
        titleEl.contentEditable = 'true';
        titleEl.classList.add('editable-active');
    }
    if (bodyEl) {
        bodyEl.contentEditable = 'true';
        bodyEl.classList.add('editable-active');
        Array.from(bodyEl.querySelectorAll('p')).forEach(p => {
            p.contentEditable = 'true';
        });
    }
    
    // Change edit button to save button
    const editBtn = document.getElementById('editNarrativeBtn');
    if (editBtn) {
        editBtn.innerHTML = '<svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        editBtn.title = 'Save narrative';
        editBtn.classList.add('bg-green-50', 'hover:bg-green-100');
    }
}

function makeNarrativeReadonly() {
    const titleEl = document.getElementById('guideNarrativeTitle');
    const bodyEl = document.getElementById('guideNarrativeBody');
    
    if (titleEl) {
        titleEl.contentEditable = 'false';
        titleEl.classList.remove('editable-active');
    }
    if (bodyEl) {
        bodyEl.contentEditable = 'false';
        bodyEl.classList.remove('editable-active');
        Array.from(bodyEl.querySelectorAll('p')).forEach(p => {
            p.contentEditable = 'false';
        });
    }
    
    // Update narratives data structure with edited content
    if (currentGuideKey && guideNarratives[currentGuideKey]) {
        guideNarratives[currentGuideKey].title = titleEl.textContent;
        guideNarratives[currentGuideKey].paragraphs = Array.from(bodyEl.querySelectorAll('p')).map(p => p.textContent);
    }
    
    // Change save button back to edit button
    const editBtn = document.getElementById('editNarrativeBtn');
    if (editBtn) {
        editBtn.innerHTML = '<svg class="w-4 h-4 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
        editBtn.title = 'Edit narrative';
        editBtn.classList.remove('bg-green-50', 'hover:bg-green-100');
    }
}

function saveNarrativeContent() {
    // Content is automatically saved when contentEditable is used
    // The makeNarrativeReadonly function handles updating the data structure
}

// Render Organizational Composition Chart (Pyramid Chart)
function renderOrgCompositionChart() {
    const container = document.getElementById('orgCompositionChart');
    if (!container) return;
    
    const isMobile = window.innerWidth < 768;
    
    // Sort data in specific order: Directors → General Manager → Dept. Managers → Division Managers → Supervisors → Rank-and-File
    const orderMap = {
        'Directors': 0,
        'General Manager': 1,
        'Dept. Managers': 2,
        'Division Managers': 3,
        'Supervisors': 4,
        'Rank-and-File': 5
    };
    const sortedData = [...organizationalComposition].sort((a, b) => {
        const orderA = orderMap[a.classification] !== undefined ? orderMap[a.classification] : 999;
        const orderB = orderMap[b.classification] !== undefined ? orderMap[b.classification] : 999;
        return orderA - orderB;
    });
    
    const maxCount = Math.max(...sortedData.map(o => o.count));
    const minCount = Math.min(...sortedData.map(o => o.count));
    
    // Use wider dimensions on desktop to fill available space
    const containerWidth = container.offsetWidth || (isMobile ? 280 : 800);
    const pyramidHeight = isMobile ? 280 : 400;
    const pyramidWidth = isMobile ? 280 : Math.min(containerWidth * 0.9, 900); // Use 90% of container width, max 900px
    const baseWidth = pyramidWidth * 0.95; // Base width of pyramid
    const topWidth = pyramidWidth * 0.15; // Top width of pyramid
    
    // Calculate width for each level (trapezoid)
    const levels = sortedData.length;
    const levelHeight = pyramidHeight / levels;
    
    let pyramidHTML = '';
    let tooltipHTML = '';
    
    // Create all gradient definitions first
    sortedData.forEach((org) => {
        const originalIndex = organizationalComposition.findIndex(o => o.classification === org.classification);
        tooltipHTML += `
            <linearGradient id="gradient-${originalIndex}" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" style="stop-color:${getGradientColor(org.color, 'start')};stop-opacity:1" />
                <stop offset="100%" style="stop-color:${getGradientColor(org.color, 'end')};stop-opacity:1" />
            </linearGradient>
        `;
    });
    
    // Render sortedData: Directors (first) at top, others below sorted by count descending
    sortedData.forEach((org, index) => {
        // Calculate width for this level (wider at bottom, narrower at top)
        // index 0 (Directors) is at top (narrowest), last index is at bottom (widest)
        const levelPosition = index / (levels - 1); // 0 at top, 1 at bottom
        const currentWidth = topWidth + (baseWidth - topWidth) * levelPosition;
        const nextWidth = index < levels - 1 
            ? topWidth + (baseWidth - topWidth) * ((index + 1) / (levels - 1))
            : baseWidth;
        
        // Calculate trapezoid points for SVG
        const leftOffset = (pyramidWidth - currentWidth) / 2;
        const nextLeftOffset = (pyramidWidth - nextWidth) / 2;
        const y1 = index * levelHeight; // Top to bottom
        const y2 = (index + 1) * levelHeight;
        
        // Create trapezoid using SVG path
        const pathPoints = `
            M ${leftOffset} ${y1}
            L ${leftOffset + currentWidth} ${y1}
            L ${nextLeftOffset + nextWidth} ${y2}
            L ${nextLeftOffset} ${y2}
            Z
        `;
        
        // Find original index for click handler
        const originalIndex = organizationalComposition.findIndex(o => o.classification === org.classification);
        
        pyramidHTML += `
            <g class="pyramid-level cursor-pointer" data-index="${originalIndex}">
                <path 
                    d="${pathPoints}"
                    fill="url(#gradient-${originalIndex})"
                    class="pyramid-segment transition-all duration-300"
                    stroke="white"
                    stroke-width="2"
                />
                <text 
                    x="${pyramidWidth / 2}" 
                    y="${y1 + levelHeight / 2}" 
                    text-anchor="middle" 
                    dominant-baseline="middle"
                    class="pyramid-text font-bold"
                    fill="white"
                    font-size="${isMobile ? '11' : '13'}"
                    style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5); pointer-events: none;"
                >
                    ${org.classification}
                </text>
                <text 
                    x="${pyramidWidth / 2}" 
                    y="${y1 + levelHeight / 2 + (isMobile ? 15 : 18)}" 
                    text-anchor="middle" 
                    dominant-baseline="middle"
                    class="pyramid-count font-semibold"
                    fill="white"
                    font-size="${isMobile ? '10' : '12'}"
                    style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5); pointer-events: none;"
                >
                    ${org.count} employees
                </text>
            </g>
        `;
    });
    
    container.innerHTML = `
        <div class="relative w-full h-full flex items-center justify-center">
            <svg width="${pyramidWidth}" height="${pyramidHeight}" viewBox="0 0 ${pyramidWidth} ${pyramidHeight}" class="pyramid-chart" preserveAspectRatio="xMidYMid meet">
                <defs>
                    ${tooltipHTML}
                </defs>
                ${pyramidHTML}
            </svg>
            <div id="pyramidTooltip" class="pyramid-tooltip hidden absolute bg-gray-900 text-white p-4 rounded-lg shadow-xl z-50 max-w-xs pointer-events-none" style="font-size: 12px; line-height: 1.6;">
                <div class="font-bold mb-2 text-base"></div>
                <div class="mb-2"><strong>Role:</strong> <span class="role-desc"></span></div>
                <div><strong>Importance:</strong> <span class="importance-desc"></span></div>
            </div>
        </div>
    `;
    
    // Add hover tooltips
    const tooltip = container.querySelector('#pyramidTooltip');
    const tooltipTitle = tooltip.querySelector('.font-bold');
    const tooltipRole = tooltip.querySelector('.role-desc');
    const tooltipImportance = tooltip.querySelector('.importance-desc');
    
    container.querySelectorAll('.pyramid-level').forEach(level => {
        const index = parseInt(level.dataset.index);
        const org = organizationalComposition[index];
        
        level.addEventListener('mouseenter', (e) => {
            tooltipTitle.textContent = org.classification;
            tooltipRole.textContent = org.role;
            tooltipImportance.textContent = org.importance;
            tooltip.classList.remove('hidden');
            
            // Position tooltip
            const rect = container.getBoundingClientRect();
            const levelRect = level.getBoundingClientRect();
            tooltip.style.left = `${levelRect.left - rect.left + levelRect.width / 2}px`;
            tooltip.style.top = `${levelRect.top - rect.top - 10}px`;
            tooltip.style.transform = 'translate(-50%, -100%)';
        });
        
        level.addEventListener('mouseleave', () => {
            tooltip.classList.add('hidden');
        });
        
        level.addEventListener('mousemove', (e) => {
            const rect = container.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Position tooltip near cursor but keep it visible
            tooltip.style.left = `${Math.min(x + 10, rect.width - 200)}px`;
            tooltip.style.top = `${Math.max(y - 10, 10)}px`;
            tooltip.style.transform = '';
        });
        
        // Add click handler for storytelling
        level.addEventListener('click', () => {
            updateOrgCompositionStory(index);
        });
    });
    
    // Re-render on window resize
    let resizeTimeout;
    const existingHandler = window.orgCompositionResizeHandler;
    if (existingHandler) {
        window.removeEventListener('resize', existingHandler);
    }
    window.orgCompositionResizeHandler = () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            renderOrgCompositionChart();
        }, 250);
    };
    window.addEventListener('resize', window.orgCompositionResizeHandler);
}

// Helper function to get gradient colors
function getGradientColor(colorClass, position) {
    const colorMap = {
        'from-blue-500 to-blue-400': { start: '#3b82f6', end: '#60a5fa' },
        'from-purple-500 to-purple-400': { start: '#a855f7', end: '#c084fc' },
        'from-indigo-500 to-indigo-400': { start: '#6366f1', end: '#818cf8' },
        'from-teal-500 to-teal-400': { start: '#14b8a6', end: '#2dd4bf' },
        'from-amber-500 to-amber-400': { start: '#f59e0b', end: '#fbbf24' },
        'from-rose-500 to-rose-400': { start: '#f43f5e', end: '#fb7185' }
    };
    
    const colors = colorMap[colorClass] || { start: '#6b7280', end: '#9ca3af' };
    return position === 'start' ? colors.start : colors.end;
}

// Update Organizational Composition Story
function updateOrgCompositionStory(index) {
    const org = organizationalComposition[index];
    const titleEl = document.getElementById('orgStoryTitle');
    const textEl = document.getElementById('orgStoryText');
    const storyBox = document.getElementById('orgCompositionStory');
    
    if (titleEl && textEl && storyBox) {
        // Add animation
        storyBox.classList.add('ring-2', 'ring-blue-300');
        titleEl.textContent = org.story.title;
        textEl.textContent = org.story.text;
        
        setTimeout(() => {
            storyBox.classList.remove('ring-2', 'ring-blue-300');
        }, 500);
    }
}

// Initialize modal functionality
function initializeModal() {
    // Close modal button
    const closeModalBtn = document.getElementById('closeModal');
    const modal = document.getElementById('departmentModal');
    const downloadExcelBtn = document.getElementById('downloadDeptExcel');
    const downloadPdfBtn = document.getElementById('downloadDeptPDF');
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });
    }

    if (downloadExcelBtn) {
        downloadExcelBtn.addEventListener('click', () => {
            if (currentModalDept) {
                downloadCurrentDeptExcel(currentModalDept);
            }
        });
    }

    if (downloadPdfBtn) {
        downloadPdfBtn.addEventListener('click', () => {
            if (currentModalDept) {
                downloadCurrentDeptPDF(currentModalDept);
            }
        });
    }
    
    // Close on backdrop click
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    }
    
    // View toggle buttons
    const viewAgeBtn = document.getElementById('viewAgeBtn');
    const viewPositionBtn = document.getElementById('viewPositionBtn');
    const viewGenderBtn = document.getElementById('viewGenderBtn');
    const viewEducationBtn = document.getElementById('viewEducationBtn');
    const viewCivilStatusBtn = document.getElementById('viewCivilStatusBtn');
    const viewCivilServiceBtn = document.getElementById('viewCivilServiceBtn');
    const viewEngagementBtn = document.getElementById('viewEngagementBtn');
    
    if (viewAgeBtn) {
        viewAgeBtn.addEventListener('click', switchToAgeView);
    }
    
    if (viewPositionBtn) {
        viewPositionBtn.addEventListener('click', switchToPositionView);
    }

    if (viewGenderBtn) {
        viewGenderBtn.addEventListener('click', switchToGenderView);
    }

    if (viewEducationBtn) {
        viewEducationBtn.addEventListener('click', switchToEducationView);
    }

    if (viewCivilStatusBtn) {
        viewCivilStatusBtn.addEventListener('click', switchToCivilStatusView);
    }

    if (viewCivilServiceBtn) {
        viewCivilServiceBtn.addEventListener('click', switchToCivilServiceView);
    }

    if (viewEngagementBtn) {
        viewEngagementBtn.addEventListener('click', switchToEngagementView);
    }
}

// Initialize Hero Section
function initializeHeroSection() {
    const heroSection = document.querySelector('.hero-section');
    const heroBackground = heroSection.querySelector('.absolute.inset-0');
    
    // Parallax effect on scroll
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        if (heroBackground) {
            heroBackground.style.transform = `translateY(${scrolled * 0.5}px)`;
        }
    });
    
    // Smooth scroll to content
    const scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        scrollIndicator.addEventListener('click', () => {
            window.scrollTo({
                top: window.innerHeight,
                behavior: 'smooth'
            });
        });
    }
}

// Department data
const departments = [
    {
        name: 'General Manager',
        employees: 31,
        avgAge: 46,
        status: 'Healthy',
        ageDistribution: [
            { range: '25-34', count: 5 },
            { range: '35-44', count: 10 },
            { range: '45-54', count: 8 },
            { range: '55-64', count: 6 },
            { range: '65+', count: 2 }
        ],
        positionBreakdown: [
            { role: 'Executive', count: 3 },
            { role: 'Director', count: 5 },
            { role: 'Manager', count: 10 },
            { role: 'Coordinator', count: 8 },
            { role: 'Specialist', count: 5 }
        ],
        genderSplit: { male: 58, female: 39, other: 3 },
        educationAttainment: [
            { level: 'High School', count: 3 },
            { level: 'Associate', count: 5 },
            { level: 'Bachelor', count: 16 },
            { level: 'Master', count: 5 },
            { level: 'Doctorate', count: 2 }
        ],
        civilStatus: [
            { status: 'Single', count: 10 },
            { status: 'Married', count: 18 },
            { status: 'Divorced', count: 2 },
            { status: 'Widowed', count: 1 }
        ],
        civilServiceEligibility: [
            { type: 'Professional', count: 23 },
            { type: 'Sub-Professional', count: 5 },
            { type: 'None', count: 3 }
        ],
        engagementStatus: [
            { type: 'Regular', count: 26 },
            { type: 'Contractual', count: 4 },
            { type: 'Job Order', count: 1 }
        ]
    },
    {
        name: 'Administrative',
        employees: 58,
        avgAge: 43,
        status: 'Stable',
        ageDistribution: [
            { range: '25-34', count: 15 },
            { range: '35-44', count: 21 },
            { range: '45-54', count: 15 },
            { range: '55-64', count: 7 },
            { range: '65+', count: 2 }
        ],
        positionBreakdown: [
            { role: 'Executive', count: 5 },
            { role: 'Director', count: 8 },
            { role: 'Manager', count: 16 },
            { role: 'Coordinator', count: 19 },
            { role: 'Specialist', count: 10 }
        ],
        genderSplit: { male: 33, female: 64, other: 3 },
        educationAttainment: [
            { level: 'High School', count: 8 },
            { level: 'Associate', count: 12 },
            { level: 'Bachelor', count: 29 },
            { level: 'Master', count: 8 },
            { level: 'Doctorate', count: 1 }
        ],
        civilStatus: [
            { status: 'Single', count: 18 },
            { status: 'Married', count: 34 },
            { status: 'Divorced', count: 5 },
            { status: 'Widowed', count: 1 }
        ],
        civilServiceEligibility: [
            { type: 'Professional', count: 42 },
            { type: 'Sub-Professional', count: 12 },
            { type: 'None', count: 4 }
        ],
        engagementStatus: [
            { type: 'Regular', count: 49 },
            { type: 'Contractual', count: 7 },
            { type: 'Job Order', count: 2 }
        ]
    },
    {
        name: 'Finance',
        employees: 52,
        avgAge: 45,
        status: 'Stable',
        ageDistribution: [
            { range: '25-34', count: 12 },
            { range: '35-44', count: 16 },
            { range: '45-54', count: 14 },
            { range: '55-64', count: 9 },
            { range: '65+', count: 1 }
        ],
        positionBreakdown: [
            { role: 'Executive', count: 4 },
            { role: 'Director', count: 6 },
            { role: 'Manager', count: 13 },
            { role: 'Coordinator', count: 18 },
            { role: 'Specialist', count: 11 }
        ],
        genderSplit: { male: 50, female: 47, other: 3 },
        educationAttainment: [
            { level: 'High School', count: 5 },
            { level: 'Associate', count: 8 },
            { level: 'Bachelor', count: 26 },
            { level: 'Master', count: 10 },
            { level: 'Doctorate', count: 3 }
        ],
        civilStatus: [
            { status: 'Single', count: 16 },
            { status: 'Married', count: 31 },
            { status: 'Divorced', count: 3 },
            { status: 'Widowed', count: 2 }
        ],
        civilServiceEligibility: [
            { type: 'Professional', count: 38 },
            { type: 'Sub-Professional', count: 10 },
            { type: 'None', count: 4 }
        ],
        engagementStatus: [
            { type: 'Regular', count: 44 },
            { type: 'Contractual', count: 6 },
            { type: 'Job Order', count: 2 }
        ]
    },
    {
        name: 'Commercial',
        employees: 66,
        avgAge: 44,
        status: 'Stable',
        ageDistribution: [
            { range: '25-34', count: 17 },
            { range: '35-44', count: 21 },
            { range: '45-54', count: 17 },
            { range: '55-64', count: 10 },
            { range: '65+', count: 1 }
        ],
        positionBreakdown: [
            { role: 'Executive', count: 5 },
            { role: 'Director', count: 10 },
            { role: 'Manager', count: 18 },
            { role: 'Coordinator', count: 23 },
            { role: 'Specialist', count: 10 }
        ],
        genderSplit: { male: 56, female: 41, other: 3 },
        educationAttainment: [
            { level: 'High School', count: 13 },
            { level: 'Associate', count: 14 },
            { level: 'Bachelor', count: 29 },
            { level: 'Master', count: 8 },
            { level: 'Doctorate', count: 2 }
        ],
        civilStatus: [
            { status: 'Single', count: 21 },
            { status: 'Married', count: 40 },
            { status: 'Divorced', count: 4 },
            { status: 'Widowed', count: 1 }
        ],
        civilServiceEligibility: [
            { type: 'Professional', count: 51 },
            { type: 'Sub-Professional', count: 12 },
            { type: 'None', count: 3 }
        ],
        engagementStatus: [
            { type: 'Regular', count: 57 },
            { type: 'Contractual', count: 7 },
            { type: 'Job Order', count: 2 }
        ]
    },
    {
        name: 'Operations',
        employees: 78,
        avgAge: 42,
        status: 'Stable',
        ageDistribution: [
            { range: '25-34', count: 22 },
            { range: '35-44', count: 27 },
            { range: '45-54', count: 18 },
            { range: '55-64', count: 8 },
            { range: '65+', count: 3 }
        ],
        positionBreakdown: [
            { role: 'Executive', count: 6 },
            { role: 'Director', count: 12 },
            { role: 'Manager', count: 23 },
            { role: 'Coordinator', count: 27 },
            { role: 'Specialist', count: 10 }
        ],
        genderSplit: { male: 68, female: 29, other: 3 },
        educationAttainment: [
            { level: 'High School', count: 19 },
            { level: 'Associate', count: 21 },
            { level: 'Bachelor', count: 27 },
            { level: 'Master', count: 10 },
            { level: 'Doctorate', count: 1 }
        ],
        civilStatus: [
            { status: 'Single', count: 25 },
            { status: 'Married', count: 49 },
            { status: 'Divorced', count: 4 },
            { status: 'Widowed', count: 1 }
        ],
        civilServiceEligibility: [
            { type: 'Professional', count: 62 },
            { type: 'Sub-Professional', count: 13 },
            { type: 'None', count: 3 }
        ],
        engagementStatus: [
            { type: 'Regular', count: 68 },
            { type: 'Contractual', count: 8 },
            { type: 'Job Order', count: 2 }
        ]
    },
    {
        name: 'Technical Services',
        employees: 63,
        avgAge: 51,
        status: 'At Risk',
        ageDistribution: [
            { range: '25-34', count: 7 },
            { range: '35-44', count: 16 },
            { range: '45-54', count: 21 },
            { range: '55-64', count: 14 },
            { range: '65+', count: 5 }
        ],
        positionBreakdown: [
            { role: 'Executive', count: 5 },
            { role: 'Director', count: 8 },
            { role: 'Manager', count: 18 },
            { role: 'Coordinator', count: 21 },
            { role: 'Specialist', count: 11 }
        ],
        genderSplit: { male: 73, female: 24, other: 3 },
        educationAttainment: [
            { level: 'High School', count: 10 },
            { level: 'Associate', count: 13 },
            { level: 'Bachelor', count: 25 },
            { level: 'Master', count: 12 },
            { level: 'Doctorate', count: 3 }
        ],
        civilStatus: [
            { status: 'Single', count: 19 },
            { status: 'Married', count: 38 },
            { status: 'Divorced', count: 3 },
            { status: 'Widowed', count: 3 }
        ],
        civilServiceEligibility: [
            { type: 'Professional', count: 47 },
            { type: 'Sub-Professional', count: 12 },
            { type: 'None', count: 4 }
        ],
        engagementStatus: [
            { type: 'Regular', count: 54 },
            { type: 'Contractual', count: 6 },
            { type: 'Job Order', count: 3 }
        ]
    }
];

// ---- Global Summary (All Departments) ----
function computeGlobalSummary() {
    const summary = {
        totalEmployees: 0,
        engagement: { Regular: 0, Contractual: 0, 'Job Order': 0 },
        gender: { male: 0, female: 0, other: 0 },
        civilStatus: {},
        education: {},
        eligibility: { Professional: 0, 'Sub-Professional': 0, None: 0 }
    };

    departments.forEach((dept) => {
        summary.totalEmployees += dept.employees;

        // Engagement
        dept.engagementStatus.forEach((e) => {
            if (summary.engagement[e.type] == null) summary.engagement[e.type] = 0;
            summary.engagement[e.type] += e.count;
        });

        // Gender (percentages -> approximate counts)
        summary.gender.male += Math.round((dept.genderSplit.male / 100) * dept.employees);
        summary.gender.female += Math.round((dept.genderSplit.female / 100) * dept.employees);
        summary.gender.other += Math.round((dept.genderSplit.other / 100) * dept.employees);

        // Civil status
        dept.civilStatus.forEach((cs) => {
            if (!summary.civilStatus[cs.status]) summary.civilStatus[cs.status] = 0;
            summary.civilStatus[cs.status] += cs.count;
        });

        // Education
        dept.educationAttainment.forEach((edu) => {
            if (!summary.education[edu.level]) summary.education[edu.level] = 0;
            summary.education[edu.level] += edu.count;
        });

        // Eligibility
        dept.civilServiceEligibility.forEach((cse) => {
            if (summary.eligibility[cse.type] == null) summary.eligibility[cse.type] = 0;
            summary.eligibility[cse.type] += cse.count;
        });
    });

    return summary;
}

function renderGlobalSummary(summary) {
    if (!summary) return;

    // Total employees
    const totalEl = document.getElementById('kpiTotalEmployees');
    if (totalEl) {
        totalEl.textContent = formatNumber(summary.totalEmployees);
    }

    // Engagement mix
    const engagementTotal =
        (summary.engagement.Regular || 0) +
        (summary.engagement.Contractual || 0) +
        (summary.engagement['Job Order'] || 0);
    const regPct = engagementTotal ? Math.round((summary.engagement.Regular / engagementTotal) * 100) : 0;
    const conPct = engagementTotal ? Math.round((summary.engagement.Contractual / engagementTotal) * 100) : 0;
    const joPct = engagementTotal ? 100 - regPct - conPct : 0;

    const engagementSummaryText = document.getElementById('kpiEngagementSummary');
    if (engagementSummaryText) {
        engagementSummaryText.textContent = `Regular ${regPct}% • Contractual ${conPct}% • JO ${joPct}%`;
    }

    const engagementBar = document.getElementById('engagementSummaryBar');
    if (engagementBar) {
        engagementBar.innerHTML = `
            <div class="bg-amber-500 h-full text-[10px] text-white flex items-center justify-center" style="width: ${regPct}%">
                ${regPct > 10 ? 'Regular' : ''}
            </div>
            <div class="bg-amber-300 h-full text-[10px] text-gray-900 flex items-center justify-center" style="width: ${conPct}%">
                ${conPct > 10 ? 'Contractual' : ''}
            </div>
            <div class="bg-amber-200 h-full text-[10px] text-gray-900 flex items-center justify-center" style="width: ${joPct}%">
                ${joPct > 10 ? 'JO' : ''}
            </div>
        `;
    }

    // Eligibility
    const eligTotal =
        (summary.eligibility.Professional || 0) +
        (summary.eligibility['Sub-Professional'] || 0) +
        (summary.eligibility.None || 0);
    const profPct = eligTotal ? Math.round((summary.eligibility.Professional / eligTotal) * 100) : 0;
    const subProfPct = eligTotal ? Math.round((summary.eligibility['Sub-Professional'] / eligTotal) * 100) : 0;
    const nonePct = eligTotal ? 100 - profPct - subProfPct : 0;

    const eligSummaryText = document.getElementById('kpiEligibilitySummary');
    if (eligSummaryText) {
        eligSummaryText.textContent = `Prof ${profPct}% • Sub-Prof ${subProfPct}% • None ${nonePct}%`;
    }

    const eligBar = document.getElementById('eligibilitySummaryBar');
    if (eligBar) {
        eligBar.innerHTML = `
            <div class="bg-teal-500 h-full text-[10px] text-white flex items-center justify-center" style="width: ${profPct}%">
                ${profPct > 10 ? 'Prof' : ''}
            </div>
            <div class="bg-teal-300 h-full text-[10px] text-gray-900 flex items-center justify-center" style="width: ${subProfPct}%">
                ${subProfPct > 10 ? 'Sub-Prof' : ''}
            </div>
            <div class="bg-teal-200 h-full text-[10px] text-gray-900 flex items-center justify-center" style="width: ${nonePct}%">
                ${nonePct > 10 ? 'None' : ''}
            </div>
        `;
    }

    // Civil status
    const civilStatusEl = document.getElementById('kpiCivilStatusSummary');
    if (civilStatusEl) {
        const entries = Object.entries(summary.civilStatus);
        const totalCS = entries.reduce((sum, [, v]) => sum + v, 0);
        civilStatusEl.innerHTML = entries
            .map(([status, count]) => {
                const pct = totalCS ? Math.round((count / totalCS) * 100) : 0;
                return `<p>${status}: <span class="font-semibold">${pct}%</span> (${formatNumber(count)})</p>`;
            })
            .join('');
    }

    // Gender
    const genderEl = document.getElementById('kpiGenderSummary');
    const genderBar = document.getElementById('genderSummaryBar');
    const genderTotal = summary.gender.male + summary.gender.female + summary.gender.other;
    const malePct = genderTotal ? Math.round((summary.gender.male / genderTotal) * 100) : 0;
    const femalePct = genderTotal ? Math.round((summary.gender.female / genderTotal) * 100) : 0;
    const otherPct = genderTotal ? 100 - malePct - femalePct : 0;

    if (genderEl) {
        genderEl.textContent = `Male ${malePct}% • Female ${femalePct}% • Other ${otherPct}%`;
    }

    if (genderBar) {
        genderBar.innerHTML = `
            <div class="bg-blue-500 h-full text-[10px] text-white flex items-center justify-center" style="width: ${malePct}%">
                ${malePct > 8 ? 'Male' : ''}
            </div>
            <div class="bg-red-400 h-full text-[10px] text-white flex items-center justify-center" style="width: ${femalePct}%">
                ${femalePct > 8 ? 'Female' : ''}
            </div>
            <div class="bg-orange-400 h-full text-[10px] text-white flex items-center justify-center" style="width: ${otherPct}%">
                ${otherPct > 8 ? 'Other' : ''}
            </div>
        `;
    }

    // Education bars
    const eduContainer = document.getElementById('educationSummaryBars');
    const eduLabelEl = document.getElementById('kpiTopEducation');
    if (eduContainer) {
        const eduEntries = Object.entries(summary.education);
        if (eduEntries.length) {
            const maxEduCount = eduEntries.reduce((max, [, v]) => (v > max ? v : max), 0);
            const topEdu = eduEntries.reduce(
                (top, curr) => (curr[1] > top[1] ? curr : top),
                eduEntries[0]
            );

            if (eduLabelEl) {
                const pctTop = Math.round((topEdu[1] / summary.totalEmployees) * 100);
                eduLabelEl.textContent = `Top: ${topEdu[0]} (${pctTop}% of employees)`;
            }

            eduContainer.innerHTML = eduEntries
                .map(([level, count]) => {
                    const heightPct = maxEduCount ? Math.max((count / maxEduCount) * 100, 10) : 10;
                    return `
                        <div class="flex-1 flex flex-col items-center justify-end h-full">
                            <div class="w-3/4 rounded-t-lg bg-gradient-to-t from-green-500 to-green-300"
                                 style="height: ${heightPct}%"></div>
                            <span class="mt-1 text-[10px] text-gray-600 text-center leading-tight">${level}</span>
                        </div>
                    `;
                })
                .join('');
        }
    }
}

function updateLastUpdatedTimestamp() {
    const el = document.getElementById('lastUpdated');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Render department cards
function renderDepartmentCards() {
    const container = document.getElementById('departmentCards');
    // Clear any existing cards to avoid duplicates on re-render
    container.innerHTML = '';
    
    departments.forEach((dept, index) => {
        const card = createDepartmentCard(dept, index);
        container.appendChild(card);
    });

    addChartInteractivity();
    refreshDepartmentBreakdownScrollAnimations();
}

// Create individual department card
function createDepartmentCard(dept, index) {
    const card = document.createElement('div');
    card.className = 'department-card bg-white rounded-3xl shadow-lg p-6 fade-in dept-animate';
    card.id = `dept-card-${index}`;
    card.setAttribute('data-animate', 'true');

    const viewContent = getCardViewContent(dept, currentCardView);
    const insights = generateInsights(dept);
    const shortInsight = truncateText(
        insights.keyInsight || `${dept.name}: explore the chart pattern and compare with other departments.`,
        190
    );
    const warningHTML = insights.warning
        ? `<div class="mt-3 bg-red-50 border border-red-100 rounded-lg p-3">
                <p class="text-xs font-semibold text-red-700 mb-1">Watch-out</p>
                <p class="text-xs text-gray-700 leading-relaxed">${escapeHtml(insights.warning)}</p>
           </div>`
        : '';
    const topRecs = (insights.recommendations || []).slice(0, 2);
    const recHTML = topRecs.length
        ? `<ul class="mt-3 space-y-1 text-xs text-gray-700 leading-relaxed">
                ${topRecs.map(r => `<li>• ${escapeHtml(r)}</li>`).join('')}
           </ul>`
        : '';

    card.innerHTML = `
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">${dept.name}</h3>
                <p class="text-sm text-gray-500">${dept.employees} employees</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">Avg Age</p>
                <p class="text-2xl font-semibold ${dept.avgAge > 50 ? 'text-red-500' : 'text-blue-600'}">${dept.avgAge}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            <div class="lg:col-span-1">
                ${viewContent}
            </div>
            <aside class="lg:col-span-1 bg-gray-50 rounded-2xl p-4 border border-gray-100">
                <p class="text-xs font-semibold text-purple-800 mb-2">Quick Insight</p>
                <p class="text-sm text-gray-700 leading-relaxed">${escapeHtml(shortInsight)}</p>
                ${warningHTML}
                ${recHTML}
            </aside>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <button class="text-sm text-blue-600 hover:text-blue-700 transition-colors">
                Click for insights →
            </button>
        </div>
    `;

    // Add click event for insights
    card.addEventListener('click', () => showDepartmentInsights(dept));

    return card;
}

function truncateText(text, maxLen) {
    if (!text) return '';
    if (text.length <= maxLen) return text;
    return text.slice(0, Math.max(0, maxLen - 1)).trimEnd() + '…';
}

// Basic HTML escaping for strings we insert into innerHTML templates
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ---- Department Breakdown: vertical smooth scroll animation (IntersectionObserver) ----
let deptCardObserver = null;

function initializeDepartmentBreakdownScrollAnimations() {
    // no-op: we create/refresh observer when cards exist
    refreshDepartmentBreakdownScrollAnimations();
}

function refreshDepartmentBreakdownScrollAnimations() {
    const cards = document.querySelectorAll('#departmentCards [data-animate="true"]');
    if (!cards.length) return;

    // Recreate observer to avoid leaking old nodes after re-render
    if (deptCardObserver) {
        deptCardObserver.disconnect();
        deptCardObserver = null;
    }

    deptCardObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                } else {
                    entry.target.classList.remove('is-visible');
                }
            });
        },
        { threshold: 0.15 }
    );

    cards.forEach((card) => deptCardObserver.observe(card));
}

function getCardViewContent(dept, view) {
    if (view === 'gender') {
        return `
            <div class="mb-2">
                <p class="text-xs text-gray-600 mb-2">Gender Split</p>
                <div class="flex h-7 rounded-full overflow-hidden mb-2">
                    <div class="card-bar bg-blue-400" style="width: ${dept.genderSplit.male}%"></div>
                    <div class="card-bar bg-red-300" style="width: ${dept.genderSplit.female}%"></div>
                    <div class="card-bar bg-orange-400" style="width: ${dept.genderSplit.other}%"></div>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-blue-600">${dept.genderSplit.male}% Male</span>
                    <span class="text-red-600">${dept.genderSplit.female}% Female</span>
                    <span class="text-orange-600">${dept.genderSplit.other}% Other</span>
                </div>
            </div>
        `;
    }

    if (view === 'education') {
        const maxCount = Math.max(...dept.educationAttainment.map(e => e.count));
        const chart = dept.educationAttainment.map(edu => {
            const height = Math.max((edu.count / maxCount) * 100, 10);
            return `
                <div class="flex flex-col items-center justify-end" style="width: ${100 / dept.educationAttainment.length}%; height: 100%;">
                    <div class="card-bar bg-gradient-to-t from-green-500 to-green-300 w-3/4 rounded-t-lg transition-all duration-300 mb-1" style="height: ${height}px; min-height: 10px;"></div>
                    <span class="text-[11px] text-gray-600 text-center leading-tight">${edu.level}</span>
                </div>
            `;
        }).join('');

        return `
            <div class="mb-2">
                <p class="text-xs text-gray-600 mb-2">Educational Attainment</p>
                <div class="h-32 flex items-end justify-between px-2" style="min-height: 128px;">
                    ${chart}
                </div>
            </div>
        `;
    }

    if (view === 'civilStatus') {
        const maxCount = Math.max(...dept.civilStatus.map(c => c.count));
        const chart = dept.civilStatus.map(cs => {
            const height = Math.max((cs.count / maxCount) * 100, 10);
            return `
                <div class="flex flex-col items-center justify-end" style="width: ${100 / dept.civilStatus.length}%; height: 100%;">
                    <div class="card-bar bg-gradient-to-t from-indigo-500 to-indigo-300 w-3/4 rounded-t-lg transition-all duration-300 mb-1" style="height: ${height}px; min-height: 10px;"></div>
                    <span class="text-[11px] text-gray-600 text-center leading-tight">${cs.status}</span>
                </div>
            `;
        }).join('');

        return `
            <div class="mb-2">
                <p class="text-xs text-gray-600 mb-2">Civil Status</p>
                <div class="h-32 flex items-end justify-between px-2" style="min-height: 128px;">
                    ${chart}
                </div>
            </div>
        `;
    }

    if (view === 'civilService') {
        const maxCount = Math.max(...dept.civilServiceEligibility.map(c => c.count));
        const chart = dept.civilServiceEligibility.map(cse => {
            const height = Math.max((cse.count / maxCount) * 100, 10);
            return `
                <div class="flex flex-col items-center justify-end" style="width: ${100 / dept.civilServiceEligibility.length}%; height: 100%;">
                    <div class="card-bar bg-gradient-to-t from-teal-500 to-teal-300 w-3/4 rounded-t-lg transition-all duration-300 mb-1" style="height: ${height}px; min-height: 10px;"></div>
                    <span class="text-[11px] text-gray-600 text-center leading-tight">${cse.type}</span>
                </div>
            `;
        }).join('');

        return `
            <div class="mb-2">
                <p class="text-xs text-gray-600 mb-2">Civil Service Eligibility</p>
                <div class="h-32 flex items-end justify-between px-2" style="min-height: 128px;">
                    ${chart}
                </div>
            </div>
        `;
    }

    if (view === 'engagement') {
        const maxCount = Math.max(...dept.engagementStatus.map(e => e.count));
        const chart = dept.engagementStatus.map(eng => {
            const height = Math.max((eng.count / maxCount) * 100, 10);
            return `
                <div class="flex flex-col items-center justify-end" style="width: ${100 / dept.engagementStatus.length}%; height: 100%;">
                    <div class="card-bar bg-gradient-to-t from-amber-500 to-amber-300 w-3/4 rounded-t-lg transition-all duration-300 mb-1" style="height: ${height}px; min-height: 10px;"></div>
                    <span class="text-[11px] text-gray-600 text-center leading-tight">${eng.type}</span>
                </div>
            `;
        }).join('');

        return `
            <div class="mb-2">
                <p class="text-xs text-gray-600 mb-2">Engagement Status</p>
                <div class="h-32 flex items-end justify-between px-2" style="min-height: 128px;">
                    ${chart}
                </div>
            </div>
        `;
    }

    // default age view
    const maxAgeCount = Math.max(...dept.ageDistribution.map(a => a.count || (a.percentage * dept.employees / 100)));
    const ageChartHTML = dept.ageDistribution.map(age => {
        const count = age.count || Math.round(age.percentage * dept.employees / 100);
        const height = Math.max((count / maxAgeCount) * 100, 10);
        const color = age.range.includes('55') || age.range.includes('65') ? 
            'bg-gradient-to-t from-red-400 to-red-300' : 
            'bg-gradient-to-t from-blue-400 to-blue-300';
        
        return `
            <div class="flex flex-col items-center justify-end" style="width: ${100/dept.ageDistribution.length}%; height: 100%;">
                <div class="age-bar card-bar ${color} w-3/4 rounded-t-lg transition-all duration-300 mb-1" style="height: ${height}px; min-height: 10px;"></div>
                <span class="text-xs text-gray-600 mt-1">${age.range}</span>
            </div>
        `;
    }).join('');

    return `
        <div class="mb-2">
            <p class="text-xs text-gray-600 mb-2">Age Distribution</p>
            <div class="h-32 flex items-end justify-between px-2" style="min-height: 128px;">
                ${ageChartHTML}
            </div>
        </div>
    `;
}

function initializeCardViewToggles() {
    const viewButtons = {
        age: document.getElementById('cardViewAge'),
        gender: document.getElementById('cardViewGender'),
        education: document.getElementById('cardViewEducation'),
        civilStatus: document.getElementById('cardViewCivilStatus'),
        civilService: document.getElementById('cardViewCivilService'),
        engagement: document.getElementById('cardViewEngagement')
    };

    Object.entries(viewButtons).forEach(([view, btn]) => {
        if (btn) {
            btn.addEventListener('click', () => setCardView(view));
        }
    });

    updateCardToggleStates();
}

function setCardView(view) {
    currentCardView = view;
    updateCardToggleStates();
    renderDepartmentCards();
    updateDepartmentBreakdownNarrative(view);
}

function updateDepartmentBreakdownNarrative(view) {
    const box = document.getElementById('departmentBreakdownNarrative');
    if (!box) return;

    const titleEl = box.querySelector('p:nth-of-type(1)');
    const textEl = box.querySelector('p:nth-of-type(2)');
    if (!titleEl || !textEl) return;

    const narratives = {
        age: {
            title: 'Age lens: pipeline health + retirement exposure',
            text: 'Scan each department for heavier bars at 55+ (risk of knowledge loss) versus stronger 25–44 segments (growth capacity). A healthy mix suggests mentorship can flow naturally; a top-heavy profile needs succession planning and accelerated hiring.'
        },
        gender: {
            title: 'Gender lens: who is represented in decision-making',
            text: 'Compare departments with balanced splits versus those dominated by one group. Balance typically broadens perspectives and improves team dynamics. If imbalance is persistent, focus on inclusive hiring, onboarding, and retention—especially in technical roles.'
        },
        education: {
            title: 'Education lens: skills depth + training priorities',
            text: 'Look for where the distribution clusters (e.g., mostly Bachelor vs. mostly High School/Associate). This helps decide whether to upskill internally (certifications, scholarships) or recruit for specialized roles needed for upcoming projects.'
        },
        civilStatus: {
            title: 'Civil status lens: benefits planning + support programs',
            text: 'This view is not about performance—it helps tailor policies. Different life circumstances shape needs around scheduling flexibility, healthcare coverage, and employee assistance. Use it to plan inclusive benefits and supportive management practices.'
        },
        civilService: {
            title: 'Civil service lens: eligibility pipeline for advancement',
            text: 'Higher eligibility levels usually widen promotion options and reduce compliance friction. If “None” is sizeable, invest in exam prep and incentives—unlocking internal mobility and strengthening the talent bench.'
        },
        engagement: {
            title: 'Engagement lens: workforce stability vs. flexibility',
            text: 'A higher Regular share typically signals stability and continuity. Contractual/Job Order mixes provide flexibility, but too much reliance can increase turnover and reduce institutional knowledge. Use this to balance budget, risk, and service continuity.'
        }
    };

    const n = narratives[view] || narratives.age;
    titleEl.textContent = n.title;
    textEl.textContent = n.text;
 }

function updateCardToggleStates() {
    const activeClasses = ['bg-blue-600', 'text-white'];
    const inactiveClasses = ['bg-white', 'text-gray-700', 'border', 'border-gray-200'];
    const buttons = {
        age: document.getElementById('cardViewAge'),
        gender: document.getElementById('cardViewGender'),
        education: document.getElementById('cardViewEducation'),
        civilStatus: document.getElementById('cardViewCivilStatus'),
        civilService: document.getElementById('cardViewCivilService'),
        engagement: document.getElementById('cardViewEngagement')
    };

    Object.entries(buttons).forEach(([view, btn]) => {
        if (!btn) return;
        btn.classList.remove(...activeClasses, ...inactiveClasses);
        if (view === currentCardView) {
            btn.classList.add(...activeClasses);
        } else {
            btn.classList.add(...inactiveClasses);
        }
    });
}

// Show department insights modal
function showDepartmentInsights(dept) {
    const modal = document.getElementById('departmentModal');
    currentModalDept = dept;
    
    // Update modal content
    document.getElementById('modalDeptName').textContent = dept.name;
    document.getElementById('modalDeptInfo').textContent = `${dept.employees} employees • Average age ${dept.avgAge} years`;
    document.getElementById('modalStatus').textContent = dept.status || 'Stable';
    document.getElementById('modalStatus').className = dept.status === 'At Risk' ? 
        'px-3 py-1 bg-red-100 text-red-600 rounded-full text-sm font-medium' :
        'px-3 py-1 bg-blue-100 text-blue-600 rounded-full text-sm font-medium';
    
    // Update KPI cards
    document.getElementById('modalMale').textContent = `${dept.genderSplit.male}%`;
    document.getElementById('modalFemale').textContent = `${dept.genderSplit.female}%`;
    document.getElementById('modalOther').textContent = `${dept.genderSplit.other}%`;
    document.getElementById('modalAvgAge').textContent = dept.avgAge;
    
    // Update total badges
    document.getElementById('modalTotalBadge').textContent = `${dept.employees} Total`;
    document.getElementById('modalTotalBadge3').textContent = `${dept.employees} Total`;
    document.getElementById('modalTotalBadge4').textContent = `${dept.employees} Total`;
    document.getElementById('modalTotalBadge5').textContent = `${dept.employees} Total`;
    document.getElementById('modalTotalBadge6').textContent = `${dept.employees} Total`;
    document.getElementById('modalTotalBadge7').textContent = `${dept.employees} Total`;
    
    // Render age distribution chart
    renderAgeDistributionChart(dept);

    // Render gender and education charts
    renderGenderBreakdownChart(dept);
    renderEducationBreakdownChart(dept);
    
    // Render new charts
    renderCivilStatusBreakdownChart(dept);
    renderCivilServiceBreakdownChart(dept);
    renderEngagementBreakdownChart(dept);

    // Department-level insights (right-side panel)
    updateDepartmentInsightsPanel(dept);
    
    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Show the view based on current card view selection
    setModalView(currentCardView);

    // Initialize chart descriptions
    updateChartDescription(currentCardView, dept);

    // Add click handlers to chart containers for description
    addChartDescriptionHandlers();
}

function updateDepartmentInsightsPanel(dept) {
    const keyEl = document.getElementById('deptInsightKey');
    const warnWrap = document.getElementById('deptInsightWarningWrap');
    const warnEl = document.getElementById('deptInsightWarning');
    const recEl = document.getElementById('deptInsightRecommendations');

    if (!keyEl || !warnWrap || !warnEl || !recEl) return;

    const insights = generateInsights(dept);

    keyEl.textContent = insights.keyInsight || `${dept.name}: review the chart patterns and use recommendations to guide next actions.`;

    if (insights.warning) {
        warnWrap.classList.remove('hidden');
        warnEl.textContent = insights.warning;
    } else {
        warnWrap.classList.add('hidden');
        warnEl.textContent = '';
    }

    recEl.innerHTML = '';
    const recs = (insights.recommendations && insights.recommendations.length)
        ? insights.recommendations
        : ['Review hiring, development, and retention levers based on the chart patterns above.'];

    recs.forEach((rec) => {
        const li = document.createElement('li');
        li.textContent = `• ${rec}`;
        recEl.appendChild(li);
    });
}

// Render age distribution chart
function renderAgeDistributionChart(dept) {
    const container = document.getElementById('ageChartContainer');
    const maxCount = Math.max(...dept.ageDistribution.map(a => a.count || Math.round((a.percentage || 0) * dept.employees / 100)));
    const maxHeight = 280; // Max height in pixels
    
    const chartHTML = dept.ageDistribution.map((age, index) => {
        const count = age.count || Math.round((age.percentage || 0) * dept.employees / 100);
        const height = (count / maxCount) * maxHeight;
        const color = age.range.includes('55') || age.range.includes('65') ? 
            'bg-gradient-to-t from-red-500 to-red-400' : 
            'bg-gradient-to-t from-blue-500 to-blue-400';
        
        return `
            <div class="flex flex-col items-center justify-end h-full cursor-pointer modal-bar" 
                 style="width: ${100/dept.ageDistribution.length}%"
                 data-type="age" data-index="${index}" data-range="${age.range}" data-count="${count}">
                <div class="${color} w-3/4 rounded-t-lg transition-all duration-300 hover:opacity-80 hover:scale-105" style="height: ${height}px"></div>
                <span class="text-xs text-gray-600 mt-2">${age.range}</span>
            </div>
        `;
    }).join('');
    
    container.innerHTML = `
        <div class="absolute bottom-0 left-0 right-0 flex items-end justify-around h-full px-4">
            ${chartHTML}
        </div>
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-500 pr-2" style="width: 30px;">
            <span>${maxCount}</span>
            <span>${Math.round(maxCount * 0.75)}</span>
            <span>${Math.round(maxCount * 0.5)}</span>
            <span>${Math.round(maxCount * 0.25)}</span>
            <span>0</span>
        </div>
    `;
    
    // Add click handlers for bars
    container.querySelectorAll('.modal-bar').forEach(bar => {
        bar.addEventListener('click', () => {
            const range = bar.dataset.range;
            const count = bar.dataset.count;
            updateModalBarStory('age', range, count, dept);
        });
    });
}

function renderGenderBreakdownChart(dept) {
    const container = document.getElementById('genderChartContainer');
    const entries = [
        { label: 'Male', value: dept.genderSplit.male, color: 'bg-blue-500' },
        { label: 'Female', value: dept.genderSplit.female, color: 'bg-red-400' },
        { label: 'Other', value: dept.genderSplit.other, color: 'bg-orange-400' }
    ];

    const maxValue = Math.max(...entries.map(e => e.value));
    const maxHeight = 240;

    const chartHTML = entries.map((entry, index) => {
        const height = (entry.value / maxValue) * maxHeight;
        return `
            <div class="flex flex-col items-center justify-end h-full cursor-pointer modal-bar" 
                 style="width: ${100 / entries.length}%"
                 data-type="gender" data-label="${entry.label}" data-value="${entry.value}">
                <div class="${entry.color} w-3/4 rounded-t-lg transition-all duration-300 hover:opacity-80 hover:scale-105" style="height: ${height}px"></div>
                <span class="text-xs text-gray-600 mt-2 text-center">${entry.label}</span>
                <span class="text-[11px] text-gray-500">${entry.value}%</span>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <div class="absolute bottom-0 left-0 right-0 flex items-end justify-around h-full px-4">
            ${chartHTML}
        </div>
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-500 pr-2" style="width: 30px;">
            <span>${maxValue}%</span>
            <span>${Math.round(maxValue * 0.75)}%</span>
            <span>${Math.round(maxValue * 0.5)}%</span>
            <span>${Math.round(maxValue * 0.25)}%</span>
            <span>0%</span>
        </div>
    `;
    
    container.querySelectorAll('.modal-bar').forEach(bar => {
        bar.addEventListener('click', () => {
            updateModalBarStory('gender', bar.dataset.label, bar.dataset.value, dept);
        });
    });
}

function renderEducationBreakdownChart(dept) {
    const container = document.getElementById('educationChartContainer');
    const maxCount = Math.max(...dept.educationAttainment.map(e => e.count));
    const maxHeight = 240;

    const chartHTML = dept.educationAttainment.map((edu, index) => {
        const height = (edu.count / maxCount) * maxHeight;
        return `
            <div class="flex flex-col items-center justify-end h-full cursor-pointer modal-bar" 
                 style="width: ${100 / dept.educationAttainment.length}%"
                 data-type="education" data-level="${edu.level}" data-count="${edu.count}">
                <div class="bg-gradient-to-t from-green-500 to-green-400 w-3/4 rounded-t-lg transition-all duration-300 hover:opacity-80 hover:scale-105" style="height: ${height}px"></div>
                <span class="text-xs text-gray-600 mt-2 text-center leading-tight">${edu.level}</span>
                <span class="text-[11px] text-gray-500">${edu.count}</span>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <div class="absolute bottom-0 left-0 right-0 flex items-end justify-around h-full px-4">
            ${chartHTML}
        </div>
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-500 pr-2" style="width: 30px;">
            <span>${maxCount}</span>
            <span>${Math.round(maxCount * 0.75)}</span>
            <span>${Math.round(maxCount * 0.5)}</span>
            <span>${Math.round(maxCount * 0.25)}</span>
            <span>0</span>
        </div>
    `;
    
    container.querySelectorAll('.modal-bar').forEach(bar => {
        bar.addEventListener('click', () => {
            updateModalBarStory('education', bar.dataset.level, bar.dataset.count, dept);
        });
    });
}

function renderCivilStatusBreakdownChart(dept) {
    const container = document.getElementById('civilStatusChartContainer');
    const maxCount = Math.max(...dept.civilStatus.map(c => c.count));
    const maxHeight = 240;

    const chartHTML = dept.civilStatus.map((cs, index) => {
        const height = (cs.count / maxCount) * maxHeight;
        return `
            <div class="flex flex-col items-center justify-end h-full cursor-pointer modal-bar" 
                 style="width: ${100 / dept.civilStatus.length}%"
                 data-type="civilStatus" data-status="${cs.status}" data-count="${cs.count}">
                <div class="bg-gradient-to-t from-indigo-500 to-indigo-400 w-3/4 rounded-t-lg transition-all duration-300 hover:opacity-80 hover:scale-105" style="height: ${height}px"></div>
                <span class="text-xs text-gray-600 mt-2 text-center leading-tight">${cs.status}</span>
                <span class="text-[11px] text-gray-500">${cs.count}</span>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <div class="absolute bottom-0 left-0 right-0 flex items-end justify-around h-full px-4">
            ${chartHTML}
        </div>
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-500 pr-2" style="width: 30px;">
            <span>${maxCount}</span>
            <span>${Math.round(maxCount * 0.75)}</span>
            <span>${Math.round(maxCount * 0.5)}</span>
            <span>${Math.round(maxCount * 0.25)}</span>
            <span>0</span>
        </div>
    `;
    
    container.querySelectorAll('.modal-bar').forEach(bar => {
        bar.addEventListener('click', () => {
            updateModalBarStory('civilStatus', bar.dataset.status, bar.dataset.count, dept);
        });
    });
}

function renderCivilServiceBreakdownChart(dept) {
    const container = document.getElementById('civilServiceChartContainer');
    const maxCount = Math.max(...dept.civilServiceEligibility.map(c => c.count));
    const maxHeight = 240;

    const chartHTML = dept.civilServiceEligibility.map((cse, index) => {
        const height = (cse.count / maxCount) * maxHeight;
        return `
            <div class="flex flex-col items-center justify-end h-full cursor-pointer modal-bar" 
                 style="width: ${100 / dept.civilServiceEligibility.length}%"
                 data-type="civilService" data-eligibility="${cse.type}" data-count="${cse.count}">
                <div class="bg-gradient-to-t from-teal-500 to-teal-400 w-3/4 rounded-t-lg transition-all duration-300 hover:opacity-80 hover:scale-105" style="height: ${height}px"></div>
                <span class="text-xs text-gray-600 mt-2 text-center leading-tight">${cse.type}</span>
                <span class="text-[11px] text-gray-500">${cse.count}</span>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <div class="absolute bottom-0 left-0 right-0 flex items-end justify-around h-full px-4">
            ${chartHTML}
        </div>
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-500 pr-2" style="width: 30px;">
            <span>${maxCount}</span>
            <span>${Math.round(maxCount * 0.75)}</span>
            <span>${Math.round(maxCount * 0.5)}</span>
            <span>${Math.round(maxCount * 0.25)}</span>
            <span>0</span>
        </div>
    `;
    
    container.querySelectorAll('.modal-bar').forEach(bar => {
        bar.addEventListener('click', () => {
            updateModalBarStory('civilService', bar.dataset.eligibility, bar.dataset.count, dept);
        });
    });
}

function renderEngagementBreakdownChart(dept) {
    const container = document.getElementById('engagementChartContainer');
    const maxCount = Math.max(...dept.engagementStatus.map(e => e.count));
    const maxHeight = 240;

    const chartHTML = dept.engagementStatus.map((eng, index) => {
        const height = (eng.count / maxCount) * maxHeight;
        return `
            <div class="flex flex-col items-center justify-end h-full cursor-pointer modal-bar" 
                 style="width: ${100 / dept.engagementStatus.length}%"
                 data-type="engagement" data-status="${eng.type}" data-count="${eng.count}">
                <div class="bg-gradient-to-t from-amber-500 to-amber-400 w-3/4 rounded-t-lg transition-all duration-300 hover:opacity-80 hover:scale-105" style="height: ${height}px"></div>
                <span class="text-xs text-gray-600 mt-2 text-center leading-tight">${eng.type}</span>
                <span class="text-[11px] text-gray-500">${eng.count}</span>
            </div>
        `;
    }).join('');

    container.innerHTML = `
        <div class="absolute bottom-0 left-0 right-0 flex items-end justify-around h-full px-4">
            ${chartHTML}
        </div>
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between text-xs text-gray-500 pr-2" style="width: 30px;">
            <span>${maxCount}</span>
            <span>${Math.round(maxCount * 0.75)}</span>
            <span>${Math.round(maxCount * 0.5)}</span>
            <span>${Math.round(maxCount * 0.25)}</span>
            <span>0</span>
        </div>
    `;
    
    container.querySelectorAll('.modal-bar').forEach(bar => {
        bar.addEventListener('click', () => {
            updateModalBarStory('engagement', bar.dataset.status, bar.dataset.count, dept);
        });
    });
}

// Update modal bar story based on clicked bar
function updateModalBarStory(type, label, value, dept) {
    const titleEl = document.getElementById('chartDescriptionTitle');
    const textEl = document.getElementById('chartDescriptionText');
    const storyBox = document.getElementById('chartDescriptionBox');
    
    if (!titleEl || !textEl || !storyBox) return;
    
    const stories = getBarStory(type, label, value, dept);
    
    // Add animation
    storyBox.classList.add('ring-2', 'ring-blue-300');
    titleEl.textContent = stories.title;
    textEl.textContent = stories.text;
    
    setTimeout(() => {
        storyBox.classList.remove('ring-2', 'ring-blue-300');
    }, 500);
}

// Get story for specific bar
function getBarStory(type, label, value, dept) {
    const pct = Math.round((value / dept.employees) * 100);
    
    if (type === 'age') {
        const stories = {
            '25-34': { title: '🌱 Young Professionals (25-34)', text: `${value} employees (${pct}%) are in their prime career-building years. This group brings fresh perspectives, technological fluency, and high energy. They're ideal candidates for innovation projects and digital transformation initiatives. Invest in their development to build your future leadership pipeline.` },
            '35-44': { title: '💼 Mid-Career Experts (35-44)', text: `${value} employees (${pct}%) represent your experienced core. They balance institutional knowledge with active career growth. Many are in or ready for leadership roles. Focus on retention strategies and advancement opportunities to prevent mid-career exodus.` },
            '45-54': { title: '🎯 Senior Contributors (45-54)', text: `${value} employees (${pct}%) bring decades of expertise. They're often your most productive workers with deep domain knowledge. Engage them as mentors and knowledge transfer agents while planning for their eventual transition.` },
            '55-64': { title: '⚠️ Pre-Retirement Zone (55-64)', text: `${value} employees (${pct}%) are approaching retirement. Critical institutional knowledge resides here. Urgently implement knowledge documentation, succession planning, and phased retirement programs. Each departure without knowledge transfer is a significant organizational risk.` },
            '65+': { title: '🏆 Legacy Experts (65+)', text: `${value} employees (${pct}%) have chosen to continue contributing beyond typical retirement age. Their dedication and expertise are invaluable. Consider flexible arrangements, consulting roles, or emeritus positions to retain their wisdom while respecting their work-life preferences.` }
        };
        return stories[label] || { title: 'Age Group Insight', text: `This age bracket contains ${value} employees.` };
    }
    
    if (type === 'gender') {
        const stories = {
            'Male': { title: '👨 Male Workforce', text: `${value}% of ${dept.name} employees are male. ${value > 60 ? 'This indicates a male-dominated environment. Consider targeted recruitment and inclusive policies to improve gender balance.' : value < 40 ? 'Males are underrepresented here. Review if role requirements or workplace culture may be deterring male candidates.' : 'A relatively balanced male representation supports diverse perspectives in decision-making.'}` },
            'Female': { title: '👩 Female Workforce', text: `${value}% of ${dept.name} employees are female. ${value > 60 ? 'Strong female representation! Ensure leadership pathways and equal advancement opportunities are maintained.' : value < 40 ? 'Female representation could be improved. Consider mentorship programs, flexible policies, and targeted outreach to attract more women.' : 'Good female representation contributes to balanced team dynamics and diverse problem-solving approaches.'}` },
            'Other': { title: '🌈 Diverse Identities', text: `${value}% identify outside the binary. This representation shows commitment to inclusivity. Continue fostering a welcoming environment through inclusive policies, training, and visible allyship from leadership.` }
        };
        return stories[label] || { title: 'Gender Insight', text: `This category represents ${value}% of the workforce.` };
    }
    
    if (type === 'education') {
        const stories = {
            'High School': { title: '📚 High School Graduates', text: `${value} employees (${pct}%) have high school education. They often fill essential operational roles. Consider upskilling programs, tuition assistance, or vocational training to help them advance while maintaining operational continuity.` },
            'Associate': { title: '🎓 Associate Degree Holders', text: `${value} employees (${pct}%) hold associate degrees. This technical foundation is valuable for specialized roles. Support their progression to bachelor programs or professional certifications to expand their capabilities.` },
            'Bachelor': { title: '🎓 Bachelor Degree Holders', text: `${value} employees (${pct}%) have bachelor degrees. This is typically the largest educated segment, forming the professional backbone. Offer graduate study support or specialized certifications to develop advanced expertise.` },
            'Master': { title: '🎓 Master Degree Holders', text: `${value} employees (${pct}%) hold master degrees. These advanced professionals bring specialized knowledge and research capabilities. Position them for strategic projects, policy development, and leadership roles.` },
            'Doctorate': { title: '🎓 Doctoral Degree Holders', text: `${value} employees (${pct}%) have doctoral degrees. This elite group provides deep expertise and research leadership. Leverage them for innovation, complex problem-solving, and mentoring advanced degree candidates.` }
        };
        return stories[label] || { title: 'Education Insight', text: `${value} employees have this education level.` };
    }
    
    if (type === 'civilStatus') {
        const stories = {
            'Single': { title: '💫 Single Employees', text: `${value} employees (${pct}%) are single. They may have more flexibility for travel, overtime, or relocation. However, ensure work-life balance policies don't inadvertently burden them with extra workload. Their career development needs are equally important.` },
            'Married': { title: '💍 Married Employees', text: `${value} employees (${pct}%) are married. They often seek stability and may prioritize work-life balance. Family-friendly policies, health benefits covering dependents, and flexible schedules support their retention and productivity.` },
            'Divorced': { title: '💔 Divorced Employees', text: `${value} employees (${pct}%) are divorced. They may be navigating significant life transitions. Ensure EAP (Employee Assistance Programs) are available and that managers are trained to support employees through personal challenges sensitively.` },
            'Widowed': { title: '🕊️ Widowed Employees', text: `${value} employees (${pct}%) are widowed. This group may need additional support and understanding. Bereavement policies, flexible leave, and compassionate management practices help them navigate work while processing loss.` }
        };
        return stories[label] || { title: 'Civil Status Insight', text: `${value} employees have this civil status.` };
    }
    
    if (type === 'civilService') {
        const stories = {
            'Professional': { title: '✅ Professional Eligibility', text: `${value} employees (${pct}%) hold Professional civil service eligibility. This is the highest qualification level, enabling them to hold supervisory and managerial positions. This strong base supports internal promotion and career advancement pathways.` },
            'Sub-Professional': { title: '📋 Sub-Professional Eligibility', text: `${value} employees (${pct}%) have Sub-Professional eligibility. They qualify for clerical and technical positions. Support their advancement by providing opportunities to take the Professional examination and relevant training.` },
            'None': { title: '⚠️ No Eligibility', text: `${value} employees (${pct}%) lack civil service eligibility. This limits their advancement in government service. Prioritize eligibility exam preparation programs, study leaves, and incentives to help them obtain certification.` }
        };
        return stories[label] || { title: 'Civil Service Insight', text: `${value} employees have this eligibility.` };
    }
    
    if (type === 'engagement') {
        const stories = {
            'Regular': { title: '✅ Regular Employees', text: `${value} employees (${pct}%) are Regular status—the backbone of organizational stability. They enjoy full benefits, job security, and career advancement opportunities. High regular employee ratios indicate organizational commitment to workforce investment.` },
            'Contractual': { title: '📝 Contractual Employees', text: `${value} employees (${pct}%) are Contractual. They provide flexibility for project-based or seasonal needs but lack job security. Consider pathways to regularization for high performers to improve retention and institutional knowledge preservation.` },
            'Job Order': { title: '📋 Job Order Workers', text: `${value} employees (${pct}%) are Job Order status—the most flexible but least secure arrangement. While useful for temporary needs, over-reliance on JO workers can affect service quality and morale. Evaluate which roles truly need this arrangement.` }
        };
        return stories[label] || { title: 'Engagement Status Insight', text: `${value} employees have this engagement status.` };
    }
    
    return { title: 'Data Insight', text: `This segment contains ${value} employees.` };
}

function addChartDescriptionHandlers() {
    const handlers = [
        { view: 'age', el: document.getElementById('ageChartContainer') },
        { view: 'position', el: document.getElementById('positionChartContainer') },
        { view: 'gender', el: document.getElementById('genderChartContainer') },
        { view: 'education', el: document.getElementById('educationChartContainer') }
    ];

    handlers.forEach(({ view, el }) => {
        if (!el) return;
        el.onclick = () => updateChartDescription(view, currentModalDept);
    });
}

function updateChartDescription(view, dept) {
    if (!dept) return;
    const titleEl = document.getElementById('chartDescriptionTitle');
    const textEl = document.getElementById('chartDescriptionText');
    if (!titleEl || !textEl) return;

    const { title, text } = getViewDescription(view, dept);
    titleEl.textContent = title;
    textEl.textContent = text;
}

function getViewDescription(view, dept) {
    if (view === 'gender') {
        return {
            title: 'Gender Story',
            text: `${dept.genderSplit.male}% male vs ${dept.genderSplit.female}% female (and ${dept.genderSplit.other}% other) paints the balance of voices in the room. A closer-to-even split often correlates with broader perspectives in decision-making. If one group dominates, prioritize inclusive hiring, mentorship, and retention programs to ensure diverse viewpoints shape policies and day-to-day operations.`
        };
    }

    if (view === 'education') {
        const topEdu = dept.educationAttainment.reduce((a, b) => b.count > a.count ? b : a, dept.educationAttainment[0]);
        return {
            title: 'Education Story',
            text: `${topEdu.level} is the most common attainment in ${dept.name}, hinting at the current ceiling of formal training. This helps decide whether to invest in upskilling—scholarships, certifications, technical courses—or to recruit higher-degree talent for specialized projects. Pair this with role needs to ensure the department has the right depth of expertise for upcoming initiatives.`
        };
    }

    if (view === 'civilStatus') {
        const topStatus = dept.civilStatus.reduce((a, b) => b.count > a.count ? b : a, dept.civilStatus[0]);
        return {
            title: 'Civil Status Story',
            text: `${topStatus.status} employees represent the largest group in ${dept.name}. Understanding civil status distribution helps in planning benefits, work-life balance programs, and support services. This data can inform policies around family leave, health benefits, and employee assistance programs tailored to different life circumstances.`
        };
    }

    if (view === 'civilService') {
        const eligibleCount = dept.civilServiceEligibility
            .filter(c => c.type !== 'None')
            .reduce((sum, c) => sum + c.count, 0);
        const eligiblePct = Math.round((eligibleCount / dept.employees) * 100);
        return {
            title: 'Civil Service Eligibility Story',
            text: `${eligiblePct}% of ${dept.name} employees hold civil service eligibility (Professional or Sub-Professional), indicating strong qualification levels for government service. This metric reflects the department compliance with civil service requirements and can guide recruitment strategies, promotion eligibility, and professional development programs.`
        };
    }

    if (view === 'engagement') {
        const regularCount = dept.engagementStatus.find(e => e.type === 'Regular')?.count || 0;
        const regularPct = Math.round((regularCount / dept.employees) * 100);
        return {
            title: 'Engagement Status Story',
            text: `${regularPct}% of ${dept.name} employees are Regular status, providing stability and continuity. The mix of Regular, Contractual, and Job Order positions reflects workforce flexibility and operational needs. Understanding this breakdown helps in workforce planning, budget allocation, and developing retention strategies for different employment types.`
        };
    }

    // age view default
    const seniorShare = dept.ageDistribution
        .filter(a => a.range.includes('55') || a.range.includes('65'))
        .reduce((sum, a) => sum + (a.count || 0), 0);
    const seniorPct = Math.round((seniorShare / dept.employees) * 100);

    return {
        title: 'Age Story',
        text: `The age spread shows pipeline health and retirement exposure. Around ${seniorPct}% are 55+, meaning valuable expertise could exit soon. Pair succession planning and mentoring now, while continuing to hire and develop younger brackets to balance experience with fresh skills. A well-shaped pyramid keeps knowledge flowing downward and career paths moving upward.`
    };
}

// Switch to age distribution view
function switchToAgeView() {
    setModalView('age');
}

// Switch to position breakdown view
function switchToPositionView() {
    setModalView('position');
}

function switchToGenderView() {
    setModalView('gender');
}

function switchToEducationView() {
    setModalView('education');
}

function switchToCivilStatusView() {
    setModalView('civilStatus');
}

function switchToCivilServiceView() {
    setModalView('civilService');
}

function switchToEngagementView() {
    setModalView('engagement');
}

function setModalView(view) {
    const views = {
        age: document.getElementById('ageDistributionView'),
        gender: document.getElementById('genderBreakdownView'),
        education: document.getElementById('educationBreakdownView'),
        civilStatus: document.getElementById('civilStatusBreakdownView'),
        civilService: document.getElementById('civilServiceBreakdownView'),
        engagement: document.getElementById('engagementBreakdownView')
    };

    const buttons = {
        age: document.getElementById('viewAgeBtn'),
        gender: document.getElementById('viewGenderBtn'),
        education: document.getElementById('viewEducationBtn'),
        civilStatus: document.getElementById('viewCivilStatusBtn'),
        civilService: document.getElementById('viewCivilServiceBtn'),
        engagement: document.getElementById('viewEngagementBtn')
    };

    Object.entries(views).forEach(([key, el]) => {
        if (!el) return;
        if (key === view) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });

    Object.entries(buttons).forEach(([key, btn]) => {
        if (!btn) return;
        btn.classList.remove('bg-blue-600', 'text-white');
        btn.classList.remove('bg-gray-100', 'text-gray-700');
        if (key === view) {
            btn.classList.add('bg-blue-600', 'text-white');
        } else {
            btn.classList.add('bg-gray-100', 'text-gray-700');
        }
    });

    updateChartDescription(view, currentModalDept);
}


// Generate insights based on department data
function generateInsights(dept) {
    const insights = {
        keyInsight: '',
        recommendations: [],
        warning: null
    };

    // Analyze age distribution
    const highRiskCount = dept.ageDistribution
        .filter(age => age.range.includes('55') || age.range.includes('65'))
        .reduce((sum, age) => sum + (age.count || Math.round((age.percentage || 0) * dept.employees / 100)), 0);
    const highRiskPercentage = (highRiskCount / dept.employees) * 100;

    if (highRiskPercentage > 20) {
        insights.warning = `${highRiskPercentage.toFixed(1)}% of the workforce is approaching retirement age. Immediate succession planning is critical.`;
        insights.recommendations.push('Implement mentorship programs for knowledge transfer');
        insights.recommendations.push('Accelerate hiring for junior positions');
    }

    // Analyze gender diversity
    const genderGap = Math.abs(dept.genderSplit.male - dept.genderSplit.female);
    
    if (genderGap < 10) {
        insights.keyInsight = `${dept.name} demonstrates excellent gender parity with only a ${genderGap}% gap. This balanced environment fosters diverse perspectives and innovation.`;
        insights.recommendations.push('Maintain current diversity initiatives');
        insights.recommendations.push('Share best practices with other departments');
    } else if (genderGap < 30) {
        insights.keyInsight = `${dept.name} shows moderate gender diversity with a ${genderGap}% gap. There is room for improvement in recruitment strategies.`;
        insights.recommendations.push('Review hiring practices to reduce bias');
        insights.recommendations.push('Implement targeted recruitment campaigns');
    } else {
        insights.keyInsight = `${dept.name} has significant gender imbalance with a ${genderGap}% gap. Strategic intervention is needed to improve diversity.`;
        insights.recommendations.push('Launch focused diversity hiring initiatives');
        insights.recommendations.push('Partner with organizations promoting underrepresented groups');
        insights.recommendations.push('Review workplace culture and policies');
    }

    // Age-specific recommendations
    if (dept.avgAge > 48) {
        insights.recommendations.push('Prioritize knowledge documentation and transfer');
        insights.recommendations.push('Consider flexible retirement transition programs');
    } else if (dept.avgAge < 40) {
        insights.recommendations.push('Invest in leadership development programs');
        insights.recommendations.push('Create clear career advancement pathways');
    }

    return insights;
}

// Add interactivity to charts
function addChartInteractivity() {
    // Add hover effects to chart bars
    document.querySelectorAll('.chart-bar, .age-bar, .card-bar').forEach(bar => {
        bar.addEventListener('mouseenter', function() {
            this.style.opacity = '0.8';
        });
        
        bar.addEventListener('mouseleave', function() {
            this.style.opacity = '1';
        });
    });
}

// Add scroll animations
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.bg-white').forEach(el => {
        observer.observe(el);
    });
}

// Utility function to format numbers
function formatNumber(num) {
    return num.toLocaleString();
}

// Utility function to calculate percentage
function calculatePercentage(value, total) {
    return ((value / total) * 100).toFixed(1);
}

// ---- Department-level downloads (Excel / PDF) ----
function slugifyDeptName(name) {
    return String(name)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'department';
}

function downloadCurrentDeptExcel(dept) {
    const insights = generateInsights(dept);

    const lines = [];

    // Header
    lines.push('Department Overview');
    lines.push(`Department,${dept.name}`);
    lines.push(`Employees,${dept.employees}`);
    lines.push(`Average Age,${dept.avgAge}`);
    lines.push(`Status,${dept.status || ''}`);
    lines.push('');

    // Age distribution
    lines.push('Age Distribution');
    lines.push('Range,Count');
    dept.ageDistribution.forEach(a => {
        const count = a.count || Math.round((a.percentage || 0) * dept.employees / 100);
        lines.push(`"${a.range}",${count}`);
    });
    lines.push('');

    // Gender split
    lines.push('Gender Split');
    lines.push('Category,Percent');
    lines.push(`Male,${dept.genderSplit.male}`);
    lines.push(`Female,${dept.genderSplit.female}`);
    lines.push(`Other,${dept.genderSplit.other}`);
    lines.push('');

    // Education
    lines.push('Educational Attainment');
    lines.push('Level,Count');
    dept.educationAttainment.forEach(e => {
        lines.push(`"${e.level}",${e.count}`);
    });
    lines.push('');

    // Civil Status
    lines.push('Civil Status');
    lines.push('Status,Count');
    dept.civilStatus.forEach(cs => {
        lines.push(`"${cs.status}",${cs.count}`);
    });
    lines.push('');

    // Civil Service
    lines.push('Civil Service Eligibility');
    lines.push('Type,Count');
    dept.civilServiceEligibility.forEach(cse => {
        lines.push(`"${cse.type}",${cse.count}`);
    });
    lines.push('');

    // Engagement
    lines.push('Engagement Status');
    lines.push('Type,Count');
    dept.engagementStatus.forEach(e => {
        lines.push(`"${e.type}",${e.count}`);
    });
    lines.push('');

    // Insights
    lines.push('Insights');
    lines.push(`Key Insight,"${(insights.keyInsight || '').replace(/"/g, '""')}"`);
    if (insights.warning) {
        lines.push(`Warning,"${insights.warning.replace(/"/g, '""')}"`);
    }
    if (insights.recommendations && insights.recommendations.length) {
        insights.recommendations.forEach((rec, idx) => {
            lines.push(`Recommendation ${idx + 1},"${rec.replace(/"/g, '""')}"`);
        });
    }

    const csvContent = lines.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `department-${slugifyDeptName(dept.name)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function downloadCurrentDeptPDF(dept) {
    const insights = generateInsights(dept);

    const win = window.open('', '_blank');
    if (!win) return;

    const now = new Date().toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });

    const html = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>${dept.name} – Workforce Breakdown</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 24px; color: #111827; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin-top: 24px; margin-bottom: 8px; }
        p { font-size: 12px; margin: 2px 0; }
        table { border-collapse: collapse; width: 100%; margin-top: 6px; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; font-size: 11px; }
        th { background: #f3f4f6; text-align: left; }
        .meta { font-size: 11px; color: #6b7280; margin-bottom: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; margin-left: 6px; }
        .badge-healthy { background:#dbeafe; color:#1d4ed8; }
        .badge-risk { background:#fee2e2; color:#b91c1c; }
        .section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>${dept.name} – Department Breakdown
        <span class="badge ${dept.status === 'At Risk' ? 'badge-risk' : 'badge-healthy'}">${dept.status || 'Status'}</span>
    </h1>
    <div class="meta">
        Employees: ${dept.employees} • Average age: ${dept.avgAge} • Exported: ${now}
    </div>

    <div class="section">
        <h2>Age Distribution</h2>
        <table>
            <thead><tr><th>Age Range</th><th>Count</th></tr></thead>
            <tbody>
                ${dept.ageDistribution.map(a => {
                    const count = a.count || Math.round((a.percentage || 0) * dept.employees / 100);
                    return `<tr><td>${a.range}</td><td>${count}</td></tr>`;
                }).join('')}
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Gender Split</h2>
        <table>
            <thead><tr><th>Category</th><th>Percent</th></tr></thead>
            <tbody>
                <tr><td>Male</td><td>${dept.genderSplit.male}%</td></tr>
                <tr><td>Female</td><td>${dept.genderSplit.female}%</td></tr>
                <tr><td>Other</td><td>${dept.genderSplit.other}%</td></tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Educational Attainment</h2>
        <table>
            <thead><tr><th>Level</th><th>Count</th></tr></thead>
            <tbody>
                ${dept.educationAttainment.map(e => `<tr><td>${e.level}</td><td>${e.count}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Civil Status</h2>
        <table>
            <thead><tr><th>Status</th><th>Count</th></tr></thead>
            <tbody>
                ${dept.civilStatus.map(cs => `<tr><td>${cs.status}</td><td>${cs.count}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Civil Service Eligibility</h2>
        <table>
            <thead><tr><th>Type</th><th>Count</th></tr></thead>
            <tbody>
                ${dept.civilServiceEligibility.map(cse => `<tr><td>${cse.type}</td><td>${cse.count}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Engagement Status</h2>
        <table>
            <thead><tr><th>Type</th><th>Count</th></tr></thead>
            <tbody>
                ${dept.engagementStatus.map(e => `<tr><td>${e.type}</td><td>${e.count}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Insights & Recommendations</h2>
        <p><strong>Key Insight:</strong> ${insights.keyInsight || ''}</p>
        ${insights.warning ? `<p><strong>Watch-out:</strong> ${insights.warning}</p>` : ''}
        ${insights.recommendations && insights.recommendations.length ? `
            <p><strong>Recommendations:</strong></p>
            <ul>
                ${insights.recommendations.map(r => `<li>${r}</li>`).join('')}
            </ul>
        ` : ''}
    </div>
</body>
</html>`;

    win.document.open();
    win.document.write(html);
    win.document.close();

    // Give the new window a moment to render before triggering print
    win.focus();
    setTimeout(() => {
        win.print();
    }, 300);
}

// Export data functionality (optional)
function exportDepartmentData() {
    const dataStr = JSON.stringify(departments, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = 'workforce-analytics-2024.json';
    link.click();
    
    URL.revokeObjectURL(url);
}

// Print functionality
function printDashboard() {
    window.print();
}

// Placeholder for real-time DistrictOne refresh hook
async function refreshDataFromDistrictOne() {
    // NOTE: In production, replace this with a real API call to DistrictOne,
    // then re-compute and render summaries/charts using the live data.
    try {
        updateLastUpdatedTimestamp();
        console.log('Refresh requested: hook this up to DistrictOne API when available.');
        if (!globalSummary) {
            globalSummary = computeGlobalSummary();
        }
        renderGlobalSummary(globalSummary);
    } catch (err) {
        console.error('Error refreshing data from DistrictOne (placeholder):', err);
        alert('Unable to refresh from DistrictOne in this demo view. Please try again later.');
    }
}

// Creators toggle removed - section is now always visible

// Keyboard navigation
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('departmentModal');
        if (modal && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }
});

// Workforce Slideshow Functionality
function initializeWorkforceSlideshow() {
    const slides = document.querySelectorAll('.workforce-slide');
    const indicators = document.querySelectorAll('.slide-indicator');
    let currentSlide = 0;
    const slideInterval = 4000; // 4 seconds per slide
    
    if (slides.length === 0) return;
    
    // Function to show a specific slide
    function showSlide(index) {
        // Remove active class from all slides and indicators
        slides.forEach(slide => slide.classList.remove('active'));
        indicators.forEach(indicator => indicator.classList.remove('active'));
        
        // Add active class to current slide and indicator
        if (slides[index]) {
            slides[index].classList.add('active');
        }
        if (indicators[index]) {
            indicators[index].classList.add('active');
        }
        
        currentSlide = index;
    }
    
    // Function to go to next slide
    function nextSlide() {
        const nextIndex = (currentSlide + 1) % slides.length;
        showSlide(nextIndex);
    }
    
    // Add click event listeners to indicators
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            showSlide(index);
            // Reset the timer when manually clicking
            clearInterval(slideshowInterval);
            slideshowInterval = setInterval(nextSlide, slideInterval);
        });
    });
    
    // Auto-advance slideshow
    let slideshowInterval = setInterval(nextSlide, slideInterval);
    
    // Initialize first slide
    showSlide(0);
    
    // Pause slideshow on hover (optional enhancement)
    const slideshowContainer = document.querySelector('.workforce-slideshow-container');
    if (slideshowContainer) {
        slideshowContainer.addEventListener('mouseenter', () => {
            clearInterval(slideshowInterval);
        });
        
        slideshowContainer.addEventListener('mouseleave', () => {
            slideshowInterval = setInterval(nextSlide, slideInterval);
        });
    }
}

// =====================================================
// DATA COMPARISON TOOLS
// =====================================================

let comparisonMode = false;
let selectedDepartments = [];

function initializeComparisonTools() {
    // Add comparison toolbar to the page
    const comparisonToolbar = document.createElement('div');
    comparisonToolbar.id = 'comparisonToolbar';
    comparisonToolbar.className = 'fixed bottom-0 left-0 right-0 bg-white shadow-2xl border-t-2 border-blue-500 p-4 transform translate-y-full transition-transform duration-300 z-50 no-print';
    comparisonToolbar.innerHTML = `
        <div class="container mx-auto">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <h3 class="text-lg font-bold text-gray-800">📊 Department Comparison</h3>
                    <span id="selectedCount" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                        0 selected
                    </span>
                </div>
                <div class="flex gap-3">
                    <button onclick="clearComparison()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                        Clear All
                    </button>
                    <button onclick="showComparisonView()" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-semibold">
                        Compare Departments
                    </button>
                    <button onclick="toggleComparisonMode()" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors">
                        ✕ Close
                    </button>
                </div>
            </div>
            <div id="selectedDeptList" class="mt-3 flex flex-wrap gap-2"></div>
        </div>
    `;
    document.body.appendChild(comparisonToolbar);
}

function toggleComparisonMode() {
    comparisonMode = !comparisonMode;
    const toolbar = document.getElementById('comparisonToolbar');
    
    if (comparisonMode) {
        toolbar.classList.remove('translate-y-full');
        enableDepartmentSelection();
    } else {
        toolbar.classList.add('translate-y-full');
        disableDepartmentSelection();
    }
}

function enableDepartmentSelection() {
    const cards = document.querySelectorAll('.department-card');
    cards.forEach(card => {
        card.style.cursor = 'pointer';
        card.style.position = 'relative';
        
        // Store original click handler
        card.dataset.originalOnclick = card.onclick;
        
        // Add new click handler for selection
        card.onclick = function(e) {
            e.stopPropagation();
            toggleDepartmentSelection(card);
        };
        
        // Add hover effect
        const overlay = document.createElement('div');
        overlay.className = 'comparison-overlay absolute inset-0 bg-blue-500 bg-opacity-0 hover:bg-opacity-10 transition-all rounded-3xl pointer-events-none';
        card.appendChild(overlay);
    });
}

function disableDepartmentSelection() {
    const cards = document.querySelectorAll('.department-card');
    cards.forEach(card => {
        // Remove overlay
        const overlay = card.querySelector('.comparison-overlay');
        if (overlay) overlay.remove();
        
        // Remove checkbox
        const checkbox = card.querySelector('.comparison-checkbox');
        if (checkbox) checkbox.remove();
        
        // Remove selection ring
        card.classList.remove('ring-4', 'ring-blue-500');
    });
}

function toggleDepartmentSelection(card) {
    const deptName = card.querySelector('h3').textContent;
    const index = selectedDepartments.findIndex(d => d.name === deptName);
    
    if (index > -1) {
        // Deselect
        selectedDepartments.splice(index, 1);
        card.classList.remove('ring-4', 'ring-blue-500');
        const checkbox = card.querySelector('.comparison-checkbox');
        if (checkbox) checkbox.remove();
    } else {
        // Select (max 4 departments)
        if (selectedDepartments.length >= 4) {
            alert('You can compare up to 4 departments at a time.');
            return;
        }
        
        const deptData = departments.find(d => d.name === deptName);
        selectedDepartments.push(deptData);
        card.classList.add('ring-4', 'ring-blue-500');
        
        // Add checkbox indicator
        const checkbox = document.createElement('div');
        checkbox.className = 'comparison-checkbox absolute top-4 right-4 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white z-10';
        checkbox.innerHTML = '✓';
        card.appendChild(checkbox);
    }
    
    updateComparisonUI();
}

function updateComparisonUI() {
    const countEl = document.getElementById('selectedCount');
    const listEl = document.getElementById('selectedDeptList');
    
    countEl.textContent = `${selectedDepartments.length} selected`;
    
    listEl.innerHTML = selectedDepartments.map(dept => `
        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
            ${dept.name} (${dept.employees} emp)
        </span>
    `).join('');
}

function clearComparison() {
    selectedDepartments = [];
    const cards = document.querySelectorAll('.department-card');
    cards.forEach(card => {
        card.classList.remove('ring-4', 'ring-blue-500');
        const checkbox = card.querySelector('.comparison-checkbox');
        if (checkbox) checkbox.remove();
    });
    updateComparisonUI();
}

function showComparisonView() {
    if (selectedDepartments.length < 2) {
        alert('Please select at least 2 departments to compare.');
        return;
    }
    
    // Create comparison modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-3xl shadow-2xl max-w-7xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">Department Comparison</h2>
                <div class="flex gap-3">
                    <button onclick="printComparison()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        🖨️ Print
                    </button>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="overflow-y-auto p-6" style="max-height: calc(90vh - 80px)">
                ${generateComparisonHTML()}
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function generateComparisonHTML() {
    const metrics = [
        { key: 'employees', label: 'Total Employees', format: v => v },
        { key: 'avgAge', label: 'Average Age', format: v => v + ' years' },
        { key: 'status', label: 'Status', format: v => v },
    ];
    
    let html = `
        <div class="grid grid-cols-1 gap-6">
            <!-- Summary Table -->
            <div class="bg-gray-50 rounded-xl p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Comparison</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-300">
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Metric</th>
                                ${selectedDepartments.map(d => `<th class="text-center py-3 px-4 font-semibold text-gray-700">${d.name}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${metrics.map(metric => `
                                <tr class="border-b border-gray-200">
                                    <td class="py-3 px-4 font-medium text-gray-700">${metric.label}</td>
                                    ${selectedDepartments.map(d => `<td class="text-center py-3 px-4">${metric.format(d[metric.key])}</td>`).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Age Distribution Comparison -->
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Age Distribution Comparison</h3>
                <div class="grid grid-cols-1 md:grid-cols-${Math.min(selectedDepartments.length, 4)} gap-4">
                    ${selectedDepartments.map(dept => `
                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-3 text-center">${dept.name}</p>
                            ${dept.ageDistribution.map(age => {
                                const count = age.count || Math.round((age.percentage || 0) * dept.employees / 100);
                                const pct = ((count / dept.employees) * 100).toFixed(1);
                                return `
                                    <div class="mb-2">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span>${age.range}</span>
                                            <span class="font-semibold">${count} (${pct}%)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: ${pct}%"></div>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Gender Comparison -->
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Gender Distribution Comparison</h3>
                <div class="grid grid-cols-1 md:grid-cols-${Math.min(selectedDepartments.length, 4)} gap-4">
                    ${selectedDepartments.map(dept => `
                        <div class="text-center">
                            <p class="text-sm font-semibold text-gray-700 mb-3">${dept.name}</p>
                            <div class="flex h-40 items-end justify-center gap-2">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 bg-blue-500 rounded-t-lg" style="height: ${dept.genderSplit.male}%"></div>
                                    <span class="text-xs mt-2">Male<br/>${dept.genderSplit.male}%</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-16 bg-red-400 rounded-t-lg" style="height: ${dept.genderSplit.female}%"></div>
                                    <span class="text-xs mt-2">Female<br/>${dept.genderSplit.female}%</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="w-16 bg-orange-400 rounded-t-lg" style="height: ${dept.genderSplit.other}%"></div>
                                    <span class="text-xs mt-2">Other<br/>${dept.genderSplit.other}%</span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Education Comparison -->
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Education Attainment Comparison</h3>
                <div class="grid grid-cols-1 md:grid-cols-${Math.min(selectedDepartments.length, 4)} gap-4">
                    ${selectedDepartments.map(dept => `
                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-3 text-center">${dept.name}</p>
                            ${dept.educationAttainment.map(edu => {
                                const pct = ((edu.count / dept.employees) * 100).toFixed(1);
                                return `
                                    <div class="mb-2">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span>${edu.level}</span>
                                            <span class="font-semibold">${edu.count} (${pct}%)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" style="width: ${pct}%"></div>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    return html;
}

function printComparison() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Department Comparison Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { color: #1f2937; margin-bottom: 10px; }
                h3 { color: #374151; margin-top: 30px; margin-bottom: 15px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
                th { background-color: #f3f4f6; font-weight: 600; }
                .header { margin-bottom: 30px; border-bottom: 2px solid #3b82f6; padding-bottom: 20px; }
                .section { margin-bottom: 30px; page-break-inside: avoid; }
                @media print {
                    @page { margin: 1cm; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Department Comparison Report</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                <p>Comparing: ${selectedDepartments.map(d => d.name).join(', ')}</p>
            </div>
            <div class="section">
                ${generateComparisonHTML()}
            </div>
            <script>
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// =====================================================
// PRINT-FRIENDLY LAYOUTS
// =====================================================

function initializePrintFeatures() {
    addPrintStyles();
    window.addEventListener('beforeprint', handleBeforePrint);
    window.addEventListener('afterprint', handleAfterPrint);
}

function addPrintStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @media print {
            .no-print { display: none !important; }
            
            body {
                background: white !important;
                background-image: none !important;
            }
            
            .bg-gradient-to-br, .bg-gradient-to-r, .backdrop-blur-sm {
                background: white !important;
                backdrop-filter: none !important;
            }
            
            .container {
                max-width: 100% !important;
                padding: 0 !important;
            }
            
            .shadow-xl, .shadow-lg, .shadow-2xl {
                box-shadow: none !important;
                border: 1px solid #e5e7eb !important;
            }
            
            .department-card, .bg-white {
                page-break-inside: avoid;
                margin-bottom: 15px;
                border: 1px solid #ddd !important;
            }
            
            @page {
                margin: 1.5cm;
            }
            
            body {
                font-size: 11pt;
                line-height: 1.5;
                color: #000;
            }
            
            h1 { font-size: 20pt; page-break-after: avoid; }
            h2 { font-size: 16pt; page-break-after: avoid; margin-top: 15pt; }
            h3 { font-size: 14pt; page-break-after: avoid; margin-top: 12pt; }
            
            #orgCompositionChart, .chart-bar, .age-bar {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .hero-section { display: none !important; }
        }
    `;
    document.head.appendChild(style);
}

function handleBeforePrint() {
    document.querySelectorAll('.hidden').forEach(el => {
        el.dataset.wasHidden = 'true';
        el.classList.remove('hidden');
    });
}

function handleAfterPrint() {
    document.querySelectorAll('[data-was-hidden="true"]').forEach(el => {
        el.classList.add('hidden');
        el.removeAttribute('data-was-hidden');
    });
}

// Add to window for global access
window.toggleComparisonMode = toggleComparisonMode;
window.clearComparison = clearComparison;
window.showComparisonView = showComparisonView;
window.printComparison = printComparison;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initializeComparisonTools();
    initializePrintFeatures();
});

// Console welcome message
console.log('%cWorkforce Analytics Dashboard 2024', 'color: #667eea; font-size: 24px; font-weight: bold;');
console.log('%cBuilt with ❤️ using Tailwind CSS', 'color: #764ba2; font-size: 14px;');
console.log('Total Employees:', departments.reduce((sum, dept) => sum + dept.employees, 0));