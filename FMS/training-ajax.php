<?php
/**
 * Training Management AJAX Handler
 * Handles all AJAX requests for training management operations
 */

require_once 'config/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = getDBConnection();

try {
    switch ($action) {
        case 'create_program':
            createTrainingProgram($pdo);
            break;
            
        case 'update_program':
            updateTrainingProgram($pdo);
            break;
            
        case 'delete_program':
            deleteTrainingProgram($pdo);
            break;
            
        case 'get_program':
            getTrainingProgram($pdo);
            break;
            
        case 'enroll_participant':
            enrollParticipant($pdo);
            break;
            
        case 'update_participant':
            updateParticipant($pdo);
            break;
            
        case 'remove_participant':
            removeParticipant($pdo);
            break;
            
        case 'create_schedule':
            createTrainingSchedule($pdo);
            break;
            
        case 'update_schedule':
            updateTrainingSchedule($pdo);
            break;
            
        case 'delete_schedule':
            deleteTrainingSchedule($pdo);
            break;
            
        case 'get_participants':
            getProgramParticipants($pdo);
            break;
            
        case 'update_completion':
            updateCompletion($pdo);
            break;
        
        case 'get_schedule':
            getSchedule($pdo);
            break;
        
        case 'retake_program':
            retakeProgram($pdo);
            break;
        
        case 'finish_orientation':
            finishOrientation($pdo);
            break;
        
        case 'update_orientation_progress':
            updateOrientationProgress($pdo);
            break;
            
        case 'create_training_entry':
        case 'get_hr1_training_needs':
            getHR1TrainingNeeds($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createTrainingProgram($pdo) {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $status = $_POST['status'] ?? 'Upcoming';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $instructor = $_POST['instructor'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO training_programs (title, category, duration, status, start_date, end_date, instructor, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $title,
        $category,
        $duration,
        $status,
        $start_date ?: null,
        $end_date ?: null,
        $instructor,
        $description
    ]);
    
    $programId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Training program created successfully',
        'program_id' => $programId
    ]);
}

function updateTrainingProgram($pdo) {
    $id = $_POST['id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $status = $_POST['status'] ?? 'Upcoming';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $instructor = $_POST['instructor'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($id) || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'ID and title are required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE training_programs 
        SET title = ?, category = ?, duration = ?, status = ?, start_date = ?, end_date = ?, instructor = ?, description = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $title,
        $category,
        $duration,
        $status,
        $start_date ?: null,
        $end_date ?: null,
        $instructor,
        $description,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training program updated successfully'
    ]);
}

function deleteTrainingProgram($pdo) {
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM training_programs WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training program deleted successfully'
    ]);
}

function getTrainingProgram($pdo) {
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM training_programs WHERE id = ?");
    $stmt->execute([$id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($program) {
        echo json_encode([
            'success' => true,
            'program' => $program
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Training program not found'
        ]);
    }
}



function updateParticipant($pdo) {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $completion_percentage = $_POST['completion_percentage'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $completed_at = ($status == 'Completed') ? date('Y-m-d H:i:s') : null;
    
    $stmt = $pdo->prepare("
        UPDATE training_participants 
        SET status = ?, completion_percentage = ?, completed_at = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $status,
        $completion_percentage,
        $completed_at,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Participant updated successfully'
    ]);
}

function removeParticipant($pdo) {
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM training_participants WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Participant removed successfully'
    ]);
}

/**
 * Return all schedule entries for a given program.
 * Used by the lectures modal when an employee finishes enrollment.
 */
function getSchedule($pdo) {
    $program_id = $_GET['program_id'] ?? 0;
    if (empty($program_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }

    // fetch the normal schedule entries
    $stmt = $pdo->prepare("SELECT session_date, session_time, session_type, location, instructor
                         FROM training_schedule
                         WHERE training_program_id = ?
                         ORDER BY session_date, session_time");
    $stmt->execute([$program_id]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // determine if we need to include a special orientation lecture
    $extraContent = '';
    $titleStmt = $pdo->prepare("SELECT title FROM training_programs WHERE id = ?");
    $titleStmt->execute([$program_id]);
    $program = $titleStmt->fetch(PDO::FETCH_ASSOC);
    if ($program && strtolower($program['title']) === 'fleet management orientation') {
        // embed the long lecture content as HTML; this will be inserted above the regular
        // schedule list on the frontend when the modal shows lectures for this program.
        $extraContent = <<<HTML
<div class="prose max-w-none mb-6" style="font-family: Arial, Helvetica, sans-serif;">
    <h3 class="text-xl font-bold text-gray-900 mb-4">Fleet Management Orientation</h3>

    <details open class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Overview</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2 mb-2">Fleet management is an important function in many organizations that rely on vehicles to deliver goods, transport people, or perform services. Companies such as logistics providers, delivery services, transportation companies, construction firms, and government agencies depend heavily on effective fleet operations.</p>
        <p>Fleet management ensures that company vehicles are properly maintained, efficiently used, safe to operate, and compliant with legal regulations. A well-managed fleet helps organizations reduce operational costs, improve productivity, and maintain high levels of safety.</p>
        <p>This orientation module introduces the fundamental concepts of fleet management, the responsibilities of fleet managers, the importance of technology in fleet operations, and the policies required to manage vehicles and drivers effectively.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Learning Objectives</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <ul class="mt-2 list-disc list-inside">
            <li>Define fleet management and explain its importance in organizations.</li>
            <li>Identify the key responsibilities of fleet managers.</li>
            <li>Understand the major components of fleet management systems.</li>
            <li>Explain the importance of vehicle maintenance and driver safety.</li>
            <li>Recognize how technology improves fleet monitoring and efficiency.</li>
            <li>Understand the policies and regulations involved in fleet operations.</li>
        </ul>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 1: Introduction to Fleet Management</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Fleet management refers to the administration, coordination, and supervision of a group of vehicles used for business operations.</p>
        <p>A fleet can include different types of vehicles such as:</p>
        <ul class="list-disc list-inside">
            <li>Passenger cars</li>
            <li>Delivery vans</li>
            <li>Trucks</li>
            <li>Motorcycles</li>
            <li>Buses</li>
            <li>Heavy equipment vehicles</li>
        </ul>
        <p>These vehicles are used by companies for activities such as:</p>
        <ul class="list-disc list-inside">
            <li>Delivering products</li>
            <li>Transporting employees</li>
        <li>Providing transportation services</li>
        <li>Supporting field operations</li>
        <li>Managing logistics and supply chain activities</li>
    </ul>
    <p>Without proper management, fleet operations can become expensive, inefficient, and unsafe. Therefore, organizations establish fleet management programs to ensure vehicles are used properly and maintained regularly.</p>
    <p>Fleet management involves planning, monitoring, maintenance, driver supervision, fuel control, and vehicle tracking.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 2: Role and Responsibilities of a Fleet Manager</summary>
        <p class="mt-2">The fleet manager is responsible for overseeing the entire fleet operation within an organization. Their role involves managing vehicles, drivers, maintenance schedules, and operational costs.</p>
        <p>Key responsibilities include:</p>
        <ul class="list-disc list-inside">
            <li><strong>Vehicle Procurement</strong> – Fleet managers determine the types of vehicles required by the organization. They analyze operational needs and choose vehicles based on:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Budget</li>
                <li>Fuel efficiency</li>
                <li>Durability</li>
                <li>Capacity</li>
                <li>Environmental impact</li>
            </ul>
            <li><strong>Vehicle Maintenance Management</strong> – Maintaining vehicles is critical for safety and performance. Fleet managers ensure that vehicles undergo regular preventive maintenance to avoid mechanical failures. Maintenance tasks include:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Oil changes</li>
                <li>Brake inspections</li>
                <li>Tire replacement</li>
                <li>Engine checks</li>
                <li>Battery testing</li>
                <li>Safety inspections</li>
            </ul>
            <li><strong>Driver Management</strong> – Drivers are an important part of fleet operations. Fleet managers are responsible for ensuring drivers:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Follow company policies</li>
                <li>Obey traffic laws</li>
                <li>Maintain safe driving behavior</li>
                <li>Report vehicle issues immediately</li>
            </ul>
            <li><strong>Cost Control</strong> – Fleet operations involve several costs such as:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Fuel</li>
                <li>Maintenance</li>
                <li>Insurance</li>
                <li>Vehicle depreciation</li>
                <li>Repairs</li>
                <li>Licensing and registration</li>
            </ul>
        </ul>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 3: Vehicle Maintenance and Lifecycle Management</summary>
        <p class="mt-2">Vehicles go through a lifecycle that includes acquisition, operation, maintenance, and eventual replacement.</p>
        <p><strong>Preventive Maintenance</strong> is scheduled servicing that prevents major vehicle failures. It ensures that vehicles remain reliable and safe. Examples include:</p>
        <ul class="list-disc list-inside">
            <li>Engine servicing</li>
            <li>Oil and filter replacement</li>
            <li>Tire rotation</li>
            <li>Brake inspection</li>
            <li>Cooling system maintenance</li>
        </ul>
        <p><strong>Corrective Maintenance</strong> occurs when a vehicle experiences mechanical problems that require repair. This may include:</p>
        <ul class="list-disc list-inside">
            <li>Engine repair</li>
            <li>Transmission issues</li>
            <li>Electrical system failures</li>
            <li>Suspension problems</li>
        </ul>
        <p><strong>Vehicle Replacement</strong> – Over time, vehicles become less efficient and more expensive to maintain. Fleet managers analyze vehicle performance data to determine when it is more cost-effective to replace vehicles rather than repair them.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 4: Fuel Management</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Fuel is one of the largest operational expenses in fleet management.</p>
        <p>Effective fuel management helps companies monitor consumption and identify inefficiencies. Fuel management strategies include:</p>
        <ul class="list-disc list-inside">
            <li>Monitoring fuel usage</li>
            <li>Implementing fuel cards</li>
            <li>Analyzing fuel consumption reports</li>
            <li>Preventing fuel theft or misuse</li>
            <li>Promoting fuel-efficient driving habits</li>
        </ul>
        <p>Drivers may also be trained to adopt eco-driving techniques, such as avoiding excessive acceleration and maintaining steady speeds.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 5: Route Planning and Dispatching</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Route planning ensures that vehicles travel the most efficient routes to reach their destinations.</p>
        <p>Effective route planning helps organizations:</p>
        <ul class="list-disc list-inside">
            <li>Reduce fuel consumption</li>
            <li>Minimize travel time</li>
            <li>Avoid traffic congestion</li>
            <li>Improve delivery schedules</li>
        </ul>
        <p>Dispatchers coordinate with drivers to assign routes and manage schedules. They may also communicate with drivers in real time to adjust routes if necessary.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 6: Technology in Fleet Management</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Modern fleet operations rely on Fleet Management Systems (FMS) to monitor vehicles and drivers.</p>
        <p>These systems use technologies such as:</p>
        <ul class="list-disc list-inside">
            <li>GPS tracking</li>
            <li>Telematics systems</li>
            <li>Mobile communication</li>
            <li>Vehicle sensors</li>
        </ul>
        <p>Through these technologies, fleet managers can monitor:</p>
        <ul class="list-disc list-inside">
            <li>Vehicle location</li>
            <li>Speed and driving behavior</li>
            <li>Fuel consumption</li>
            <li>Engine performance</li>
            <li>Maintenance alerts</li>
        </ul>
        <p>Real-time monitoring improves operational efficiency and enhances safety.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 7: Safety and Compliance</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Safety is a major priority in fleet operations. Organizations must ensure that both vehicles and drivers comply with government regulations and company policies.</p>
        <p>Safety measures include:</p>
        <ul class="list-disc list-inside">
            <li>Regular vehicle inspections</li>
            <li>Driver safety training</li>
            <li>Monitoring driver behavior</li>
            <li>Enforcing speed limits</li>
            <li>Ensuring proper vehicle loading</li>
        </ul>
        <p>Companies must also comply with legal requirements such as:</p>
        <ul class="list-disc list-inside">
            <li>Vehicle registration</li>
            <li>Insurance coverage</li>
            <li>Emission standards</li>
            <li>Driver licensing requirements</li>
        </ul>
        <p>Failure to comply with these regulations may result in penalties, accidents, or operational disruptions.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 8: Benefits of Effective Fleet Management</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Organizations that implement effective fleet management practices experience several benefits. These include:</p>
        <ul class="list-disc list-inside">
            <li><strong>Reduced Costs</strong> – Proper monitoring and maintenance reduce fuel expenses, repair costs, and vehicle downtime.</li>
            <li><strong>Increased Productivity</strong> – Efficient route planning and vehicle tracking help drivers complete tasks faster.</li>
            <li><strong>Improved Safety</strong> – Monitoring driver behavior and maintaining vehicles reduces the risk of accidents.</li>
            <li><strong>Better Decision-Making</strong> – Fleet management systems provide data and reports that help managers make informed decisions.</li>
            <li><strong>Environmental Sustainability</strong> – Fuel-efficient vehicles and eco-driving practices help reduce environmental impact.</li>
        </ul>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Summary</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Fleet management is a critical function for organizations that rely on vehicles for daily operations. It involves managing vehicles, drivers, maintenance schedules, fuel usage, and operational costs.</p>
        <p>By implementing proper fleet management strategies and using modern technology, organizations can ensure that their fleet operates efficiently, safely, and cost-effectively.</p>
        <p>A well-organized fleet management system not only improves operational performance but also contributes to long-term business success.</p>
        </div>
    </details>
</div>
HTML;
    } elseif ($program && in_array(strtolower(str_replace('&', 'and', trim($program['title']))), ['safety and compliance orientation', 'bbbbbb'])) {
        $extraContent = <<<HTML
<div class="prose max-w-none mb-6" style="font-family: Arial, Helvetica, sans-serif;">
    <h3 class="text-xl font-bold text-gray-900 mb-4">Safety & Compliance Orientation</h3>

    <details open class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Overview</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2 mb-2">Safety and Compliance Orientation is an essential training program designed to ensure that employees understand the importance of maintaining a safe workplace while following company policies, laws, and industry regulations. Organizations must prioritize safety to protect employees, customers, equipment, and company resources.</p>
        <p>Safety focuses on preventing accidents, injuries, and hazards within the workplace. Compliance, on the other hand, ensures that employees follow legal requirements, ethical standards, and organizational policies.</p>
        <p>A strong safety and compliance culture encourages employees to act responsibly, follow established procedures, and remain aware of potential risks. By understanding and practicing safety guidelines and compliance standards, employees contribute to a secure, productive, and professional working environment.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Learning Objectives</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2 mb-2">After completing this orientation, learners should be able to:</p>
        <ul class="list-disc list-inside">
            <li>Understand the importance of workplace safety and compliance.</li>
            <li>Identify potential workplace hazards and risks.</li>
            <li>Follow company safety policies and procedures.</li>
            <li>Understand the role of employees in maintaining compliance.</li>
            <li>Recognize emergency procedures and reporting systems.</li>
            <li>Promote a culture of responsibility and safety in the workplace.</li>
        </ul>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 1: Introduction to Workplace Safety</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Workplace safety refers to the policies, procedures, and actions implemented to protect employees and prevent accidents or injuries. Every organization has a responsibility to ensure that employees can perform their tasks in a safe and healthy environment.</p>
        <p>A safe workplace helps prevent:</p>
        <ul class="list-disc list-inside">
            <li>Injuries and accidents</li>
            <li>Equipment damage</li>
            <li>Health-related issues</li>
            <li>Operational disruptions</li>
            <li>Financial losses</li>
        </ul>
        <p>Employees must always remain aware of their surroundings and follow safety procedures to reduce risks.</p>
        <p>Creating a strong safety culture means that everyone in the organization shares responsibility for safety, from management to employees.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 2: Understanding Compliance</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Compliance means following the rules, regulations, policies, and ethical standards required by the organization and government authorities.</p>
        <p>Organizations must comply with several types of regulations, including:</p>
        <ul class="list-disc list-inside">
            <li>Labor laws</li>
            <li>Occupational health and safety standards</li>
            <li>Environmental regulations</li>
            <li>Data protection policies</li>
            <li>Industry-specific guidelines</li>
        </ul>
        <p>Failure to follow these regulations can lead to serious consequences such as:</p>
        <ul class="list-disc list-inside">
            <li>Legal penalties</li>
            <li>Fines and sanctions</li>
            <li>Damage to company reputation</li>
            <li>Loss of business opportunities</li>
        </ul>
        <p>Employees must understand that compliance is not only a legal requirement but also an ethical responsibility.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 3: Identifying Workplace Hazards</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">A workplace hazard is anything that has the potential to cause harm or injury. Identifying hazards early helps prevent accidents and protects employees.</p>
        <p>Common workplace hazards include:</p>
        <p><strong>Physical Hazards</strong></p>
        <p>These hazards arise from environmental conditions such as:</p>
        <ul class="list-disc list-inside">
            <li>Slippery floors</li>
            <li>Poor lighting</li>
            <li>Excessive noise</li>
            <li>Cluttered work areas</li>
            <li>Unsafe workspaces</li>
        </ul>
        <p>Employees should report these conditions immediately so that corrective action can be taken.</p>
        <p><strong>Equipment and Machinery Hazards</strong></p>
        <p>Operating machinery or tools without proper training can result in serious accidents. Employees must:</p>
        <ul class="list-disc list-inside">
            <li>Follow equipment instructions carefully</li>
            <li>Avoid using damaged tools</li>
            <li>Ensure machines are properly maintained</li>
        </ul>
        <p>Proper training and supervision are essential to ensure safe equipment use.</p>
        <p><strong>Chemical Hazards</strong></p>
        <p>Some workplaces involve chemicals that may cause health risks. Employees must understand how to handle chemicals safely, store chemicals properly, and read warning labels and safety instructions. Organizations often provide Safety Data Sheets (SDS) that contain detailed information about chemical hazards.</p>
        <p><strong>Ergonomic Hazards</strong></p>
        <p>Ergonomic hazards occur when employees experience physical strain due to improper posture, repetitive tasks, or poorly designed workstations. Examples include back pain from poor seating, wrist strain from repetitive typing, and neck strain from improper monitor positioning. Proper workstation setup and regular breaks help reduce ergonomic risks.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 4: Safety Policies and Workplace Rules</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Organizations implement safety policies to guide employees in maintaining a safe working environment.</p>
        <p>These policies may include:</p>
        <ul class="list-disc list-inside">
            <li>Following standard operating procedures</li>
            <li>Keeping work areas clean and organized</li>
            <li>Using equipment only when trained</li>
            <li>Reporting unsafe conditions</li>
            <li>Following emergency protocols</li>
        </ul>
        <p>Employees are expected to read, understand, and follow these policies at all times.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 5: Personal Protective Equipment (PPE)</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Personal Protective Equipment (PPE) refers to protective clothing or equipment used to minimize exposure to workplace hazards.</p>
        <p>Examples of PPE include:</p>
        <ul class="list-disc list-inside">
            <li>Safety helmets</li>
            <li>Protective gloves</li>
            <li>Safety goggles</li>
            <li>Face masks</li>
            <li>Safety shoes</li>
            <li>Protective clothing</li>
        </ul>
        <p>Employees must wear PPE whenever required and ensure that the equipment is used properly.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 6: Emergency Preparedness</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Emergency preparedness ensures that employees know how to respond during unexpected situations.</p>
        <p>Common workplace emergencies include:</p>
        <ul class="list-disc list-inside">
            <li>Fires</li>
            <li>Natural disasters</li>
            <li>Medical emergencies</li>
            <li>Equipment failures</li>
        </ul>
        <p>Employees should be familiar with:</p>
        <ul class="list-disc list-inside">
            <li>Emergency exits</li>
            <li>Evacuation routes</li>
            <li>Emergency assembly areas</li>
            <li>Fire extinguishers and alarms</li>
            <li>Emergency contact numbers</li>
        </ul>
        <p>Remaining calm and following instructions during emergencies can help prevent further harm.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 7: Incident and Hazard Reporting</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Reporting incidents and hazards is an important responsibility for all employees. Immediate reporting helps organizations respond quickly and prevent similar incidents in the future.</p>
        <p>Employees should report:</p>
        <ul class="list-disc list-inside">
            <li>Workplace injuries</li>
            <li>Near-miss incidents</li>
            <li>Unsafe equipment</li>
            <li>Hazardous conditions</li>
        </ul>
        <p>Accurate reporting allows management to investigate incidents and improve safety procedures.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 8: Ethics and Professional Conduct</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Compliance also includes maintaining ethical behavior and professionalism in the workplace.</p>
        <p>Employees are expected to:</p>
        <ul class="list-disc list-inside">
            <li>Follow company policies and guidelines</li>
            <li>Respect colleagues and workplace rules</li>
            <li>Avoid misconduct or illegal activities</li>
            <li>Protect company property and information</li>
            <li>Maintain honesty and integrity</li>
        </ul>
        <p>Ethical conduct strengthens trust within the organization and supports a positive workplace environment.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 9: Benefits of Safety and Compliance Programs</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Organizations that prioritize safety and compliance experience several benefits.</p>
        <p><strong>Reduced Workplace Accidents</strong> – Employees become more aware of risks and follow safety procedures.</p>
        <p><strong>Legal and Regulatory Protection</strong> – Following laws and regulations helps organizations avoid penalties and legal issues.</p>
        <p><strong>Improved Employee Well-being</strong> – Employees feel safer and more comfortable working in a secure environment.</p>
        <p><strong>Increased Productivity</strong> – When employees feel safe, they can focus better on their tasks and perform more efficiently.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Summary</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Safety and Compliance Orientation ensures that employees understand the importance of following workplace safety procedures and regulatory requirements. By identifying hazards, using protective equipment, following company policies, and reporting incidents, employees contribute to maintaining a safe and responsible workplace.</p>
        <p>Safety is a shared responsibility that requires cooperation between management and employees. When organizations prioritize safety and compliance, they create a workplace environment that promotes security, professionalism, and long-term organizational success.</p>
        </div>
    </details>
</div>
HTML;
    } elseif ($program && strtolower(trim($program['title'])) === 'operations department orientation') {
        $extraContent = <<<HTML
<div class="prose max-w-none mb-6" style="font-family: Arial, Helvetica, sans-serif;">
    <h3 class="text-xl font-bold text-gray-900 mb-4">Operations Department Orientation</h3>

    <details open class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Overview</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2 mb-2">The Operations Department plays a critical role in ensuring that an organization's daily activities run efficiently and effectively. It is responsible for managing the processes that transform resources such as labor, materials, and technology into products or services delivered to customers.</p>
        <p>Operations management focuses on planning, organizing, directing, and controlling business operations to achieve organizational goals. This department ensures that services are delivered on time, resources are used efficiently, and quality standards are maintained.</p>
        <p>In many organizations, especially those involved in logistics, transportation, manufacturing, and service industries, the operations department acts as the core of business activities because it directly handles the processes that produce results for customers.</p>
        <p>This orientation module introduces employees to the structure, functions, and responsibilities of the operations department, as well as the importance of teamwork, efficiency, and quality control in daily operations.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Learning Objectives</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2 mb-2">After completing this orientation, learners should be able to:</p>
        <ul class="list-disc list-inside">
            <li>Understand the role and importance of the operations department.</li>
            <li>Identify the major responsibilities of operations management.</li>
            <li>Understand the workflow and processes involved in operations.</li>
            <li>Recognize the importance of coordination with other departments.</li>
            <li>Learn how operations contribute to organizational productivity and customer satisfaction.</li>
        </ul>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 1: Introduction to Operations Management</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Operations management refers to the process of overseeing, designing, and controlling the production of goods or services. It ensures that resources are used effectively to produce quality outputs while meeting customer demands.</p>
        <p>The operations department is responsible for managing activities such as:</p>
        <ul class="list-disc list-inside">
            <li>Production or service delivery</li>
            <li>Resource allocation</li>
            <li>Process management</li>
            <li>Quality control</li>
            <li>Operational planning</li>
        </ul>
        <p>Every organization depends on operations to ensure that work is performed efficiently and that customers receive the expected service or product.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 2: Structure of the Operations Department</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">The operations department may include several teams or units depending on the type of organization.</p>
        <p>Common roles within the operations department include:</p>
        <p><strong>Operations Manager</strong></p>
        <p>The operations manager oversees all operational activities and ensures that business processes run smoothly. They coordinate with other departments and monitor operational performance.</p>
        <p><strong>Supervisors or Team Leaders</strong></p>
        <p>Supervisors manage daily tasks and guide employees to ensure work is completed according to company standards and schedules.</p>
        <p><strong>Operations Staff</strong></p>
        <p>Operations staff perform the daily tasks required to deliver products or services. Their responsibilities depend on the organization's industry.</p>
        <p><strong>Support Personnel</strong></p>
        <p>Support staff assist with administrative tasks, scheduling, reporting, and coordination with other departments.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 3: Core Functions of the Operations Department</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">The operations department performs several important functions that help the organization achieve its goals.</p>
        <p><strong>Operational Planning</strong></p>
        <p>Operational planning involves organizing resources and schedules to ensure that daily activities run smoothly. This includes planning work schedules, allocating tasks, and ensuring that equipment and materials are available. Proper planning helps reduce delays and improves productivity.</p>
        <p><strong>Resource Management</strong></p>
        <p>Operations teams manage various resources such as:</p>
        <ul class="list-disc list-inside">
            <li>Employees</li>
            <li>Equipment</li>
            <li>Materials</li>
            <li>Technology</li>
            <li>Facilities</li>
        </ul>
        <p>Efficient use of resources helps reduce waste and improves operational efficiency.</p>
        <p><strong>Process Management</strong></p>
        <p>Process management involves designing and improving workflows to ensure that tasks are completed efficiently. Organizations often analyze their operational processes to identify bottlenecks, inefficiencies, and opportunities for improvement. Improving processes helps increase productivity and service quality.</p>
        <p><strong>Quality Control</strong></p>
        <p>Quality control ensures that products or services meet the required standards before reaching customers. Quality control activities may include inspecting products, monitoring service performance, reviewing operational reports, and identifying errors or defects. Maintaining high quality helps organizations build customer trust and satisfaction.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 4: Coordination with Other Departments</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">The operations department works closely with other departments within the organization.</p>
        <p><strong>Human Resources Department</strong> – Coordinates staffing, employee training, and workforce management.</p>
        <p><strong>Finance Department</strong> – Manages budgets, operational costs, and financial planning.</p>
        <p><strong>Customer Service Department</strong> – Handles customer inquiries, feedback, and service concerns.</p>
        <p><strong>Logistics or Supply Chain Department</strong> – Ensures materials and supplies are delivered on time to support operations.</p>
        <p>Effective communication and collaboration between departments help ensure that operations run smoothly.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 5: Performance Monitoring and Reporting</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Operations departments regularly monitor performance to ensure efficiency and productivity.</p>
        <p>Key performance indicators (KPIs) commonly monitored include:</p>
        <ul class="list-disc list-inside">
            <li>Productivity levels</li>
            <li>Operational costs</li>
            <li>Service delivery time</li>
            <li>Customer satisfaction</li>
            <li>Error or defect rates</li>
        </ul>
        <p>Performance reports help managers identify areas that require improvement and make better operational decisions.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 6: Use of Technology in Operations</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Modern operations departments rely on technology to manage tasks more efficiently.</p>
        <p>Examples of technologies used include:</p>
        <ul class="list-disc list-inside">
            <li>Enterprise Resource Planning (ERP) systems</li>
            <li>Fleet Management Systems</li>
            <li>Inventory management software</li>
            <li>Scheduling systems</li>
            <li>Data analytics tools</li>
        </ul>
        <p>These technologies help managers monitor operations in real time, track performance, and improve decision-making.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 7: Workplace Safety and Compliance in Operations</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Safety is an important aspect of operations management. Employees must follow safety guidelines to prevent accidents and maintain a safe working environment.</p>
        <p>Operations teams must also comply with company policies and government regulations related to workplace safety, labor standards, environmental regulations, and operational procedures. Following safety and compliance rules helps protect employees and ensures smooth business operations.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 8: Importance of Teamwork in Operations</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Operations departments rely heavily on teamwork because many processes involve coordination among multiple employees.</p>
        <p>Successful operations teams demonstrate:</p>
        <ul class="list-disc list-inside">
            <li>Clear communication</li>
            <li>Cooperation</li>
            <li>Accountability</li>
            <li>Problem-solving skills</li>
            <li>Commitment to quality</li>
        </ul>
        <p>When employees work together effectively, operations become more efficient and productive.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 9: Benefits of an Effective Operations Department</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Organizations with strong operations management gain several advantages:</p>
        <p><strong>Improved Efficiency</strong> – Proper planning and resource management reduce wasted time and effort.</p>
        <p><strong>Higher Quality Output</strong> – Quality control processes ensure products and services meet customer expectations.</p>
        <p><strong>Cost Reduction</strong> – Efficient operations help reduce unnecessary expenses.</p>
        <p><strong>Better Customer Satisfaction</strong> – When services and products are delivered efficiently, customers are more satisfied.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Summary</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">The operations department is responsible for managing the daily activities that keep an organization running smoothly. It focuses on planning, coordinating resources, monitoring performance, and ensuring quality service delivery.</p>
        <p>Through effective operations management, organizations can improve efficiency, reduce costs, and deliver better services to customers. Employees within the operations department must work collaboratively, follow operational procedures, and maintain high standards of performance to support the organization's overall success.</p>
        </div>
    </details>
</div>
HTML;
    } elseif ($program && strtolower(trim($program['title'])) === 'new hire orientation') {
        $extraContent = <<<HTML
<div class="prose max-w-none mb-6" style="font-family: Arial, Helvetica, sans-serif;">
    <h3 class="text-xl font-bold text-gray-900 mb-4">New Hire Orientation</h3>

    <details open class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Overview</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2 mb-2">New Hire Orientation is the process of introducing newly hired employees to the organization, its culture, policies, responsibilities, and expectations. It serves as the first formal step in helping new employees understand how the company operates and how they can successfully perform their roles.</p>
        <p>This orientation program helps new employees become familiar with the workplace environment, organizational structure, and the rules that guide daily operations. It also provides important information about employee benefits, workplace safety, company policies, and job responsibilities.</p>
        <p>A well-structured orientation program allows new hires to feel welcomed, confident, and prepared to contribute to the organization. It helps reduce confusion, improve productivity, and strengthen employee engagement from the beginning of their employment.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Learning Objectives</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2 mb-2">After completing this orientation, new employees should be able to:</p>
        <ul class="list-disc list-inside">
            <li>Understand the company's mission, vision, and values.</li>
            <li>Become familiar with company policies and workplace rules.</li>
            <li>Understand their roles, responsibilities, and expectations.</li>
            <li>Learn about the organization's structure and departments.</li>
            <li>Recognize workplace safety procedures and compliance requirements.</li>
            <li>Become aware of employee benefits and support systems.</li>
        </ul>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 1: Introduction to the Organization</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">During the orientation process, new employees are introduced to the organization's background and purpose. Understanding the company's mission and vision helps employees see how their roles contribute to the overall goals of the organization.</p>
        <p><strong>Mission</strong> – The mission statement explains the purpose of the organization and what it aims to achieve for its customers and stakeholders.</p>
        <p><strong>Vision</strong> – The vision describes the long-term goals and future direction of the organization.</p>
        <p><strong>Core Values</strong> – Core values represent the principles that guide employee behavior and decision-making. These values often include Integrity, Teamwork, Accountability, Excellence, and Customer focus. Understanding these values helps employees align their work with the organization's culture.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 2: Organizational Structure</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">New employees are introduced to the structure of the organization and the different departments that work together to achieve business objectives.</p>
        <p>Common departments include:</p>
        <ul class="list-disc list-inside">
            <li><strong>Human Resources Department</strong> – Responsible for recruitment, employee relations, training, and workforce development.</li>
            <li><strong>Operations Department</strong> – Manages the day-to-day activities that ensure products or services are delivered efficiently.</li>
            <li><strong>Finance Department</strong> – Handles budgeting, financial planning, and financial reporting.</li>
            <li><strong>Customer Service Department</strong> – Addresses customer concerns, inquiries, and service support.</li>
        </ul>
        <p>Understanding the organizational structure helps employees know who to contact for different concerns or tasks.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 3: Roles and Responsibilities</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Each employee is assigned specific roles and responsibilities within the organization. During orientation, new hires learn about their job description, expected performance standards, work schedules, and reporting relationships.</p>
        <p>Employees are expected to perform their tasks professionally and contribute to the team's success. Managers and supervisors also provide guidance and support to help employees perform their duties effectively.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 4: Company Policies and Workplace Rules</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Organizations establish policies to maintain professionalism, fairness, and consistency in the workplace.</p>
        <p>Some common workplace policies include:</p>
        <p><strong>Attendance and Punctuality</strong> – Employees are expected to report to work on time and follow their assigned schedules.</p>
        <p><strong>Code of Conduct</strong> – Employees must behave professionally and treat colleagues with respect.</p>
        <p><strong>Dress Code</strong> – Some organizations require employees to follow specific dress standards appropriate for the workplace.</p>
        <p><strong>Confidentiality</strong> – Employees must protect sensitive company information and maintain data privacy.</p>
        <p>Understanding these policies helps maintain discipline and professionalism in the workplace.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 5: Workplace Safety and Compliance</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Safety is a critical part of every workplace. During orientation, new hires are introduced to safety procedures that help prevent accidents and injuries. Safety training may include emergency procedures, fire safety awareness, hazard identification, and proper use of equipment.</p>
        <p>Employees must also comply with workplace regulations and follow safety guidelines to maintain a safe working environment.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 6: Employee Benefits and Support Programs</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Organizations provide various benefits to support employees' well-being and job satisfaction. Common employee benefits may include health insurance, paid leave and vacation days, training and development opportunities, employee assistance programs, and retirement or savings plans.</p>
        <p>Understanding these benefits helps employees take advantage of the resources available to them.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 7: Workplace Communication</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Effective communication is essential for successful teamwork. Employees are encouraged to maintain open communication with supervisors and colleagues. Communication in the workplace may include team meetings, email correspondence, reporting systems, and collaboration tools. Employees should feel comfortable asking questions and seeking clarification when necessary.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 8: Training and Development</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">Many organizations provide ongoing training programs to help employees develop their skills and advance their careers. Training opportunities may include job-specific technical training, leadership development programs, professional development workshops, and online learning modules. Continuous learning helps employees improve their performance and grow within the organization.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Lesson 9: Building a Positive Work Culture</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">A positive work culture promotes teamwork, respect, and collaboration. Employees are encouraged to support each other and contribute to a productive work environment. A strong workplace culture encourages teamwork and cooperation, respect for diversity, accountability and responsibility, and innovation and creativity. When employees feel valued and respected, they are more motivated to perform well and contribute to the organization's success.</p>
        </div>
    </details>

    <details class="mb-4 rounded border border-gray-200 p-4">
        <summary class="font-bold text-lg cursor-pointer text-gray-900" style="font-size: 1.125rem;">Module Summary</summary>
        <div class="mt-2 text-gray-900 leading-relaxed font-normal" style="font-size: 13px; font-weight: 400 !important;">
        <p class="mt-2">New Hire Orientation is an essential part of the onboarding process that helps employees become familiar with the organization, its policies, and their roles within the company. Through orientation, new hires gain the knowledge and confidence needed to perform their responsibilities effectively.</p>
        <p>By understanding company values, workplace expectations, and available resources, employees can successfully integrate into the organization and contribute to its growth and success.</p>
        <p>A well-conducted orientation program helps create a positive first experience for employees and sets the foundation for long-term professional development.</p>
        </div>
    </details>
</div>
HTML;
    }

    echo json_encode([
        'success' => true,
        'schedule' => $schedule,
        'extra_content' => $extraContent
    ]);
}

/**
 * Reset current user's progress on a program so they can retake orientation.
 */
function enrollParticipant($pdo) {
    $program_id = $_POST['program_id'] ?? 0;
    $employee_id = $_POST['employee_id'] ?? 0;
    $enrolled_name = trim($_POST['full_name'] ?? '') ?: null;
    $health_condition = trim($_POST['health_condition'] ?? '') ?: null;
    
    if (empty($program_id) || empty($employee_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID and Employee ID are required']);
        return;
    }
    
    // We now allow multiple enrollments for the same program, so we just insert a new record
    $stmt = $pdo->prepare("
        INSERT INTO training_participants (training_program_id, employee_id, enrolled_name, health_condition, status, completion_percentage)
        VALUES (?, ?, ?, ?, 'Upcoming', 0)
    ");
    
    $stmt->execute([$program_id, $employee_id, $enrolled_name, $health_condition]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Participant enrolled successfully'
    ]);
}


function retakeProgram($pdo) {
    // retake logic now just returns success to trigger the enrollment modal on the frontend
    echo json_encode(['success' => true, 'message' => 'Orientation reset, you can retake it now']);
}

function createTrainingSchedule($pdo) {
    $program_id = $_POST['program_id'] ?? 0;
    $session_date = $_POST['session_date'] ?? '';
    $session_time = $_POST['session_time'] ?? '';
    $session_type = $_POST['session_type'] ?? '';
    $location = $_POST['location'] ?? '';
    $instructor = $_POST['instructor'] ?? '';
    
    if (empty($program_id) || empty($session_date) || empty($session_time)) {
        echo json_encode(['success' => false, 'message' => 'Program ID, date, and time are required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO training_schedule (training_program_id, session_date, session_time, session_type, location, instructor)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $program_id,
        $session_date,
        $session_time,
        $session_type,
        $location,
        $instructor
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training schedule created successfully'
    ]);
}

function updateTrainingSchedule($pdo) {
    $id = $_POST['id'] ?? 0;
    $session_date = $_POST['session_date'] ?? '';
    $session_time = $_POST['session_time'] ?? '';
    $session_type = $_POST['session_type'] ?? '';
    $location = $_POST['location'] ?? '';
    $instructor = $_POST['instructor'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE training_schedule 
        SET session_date = ?, session_time = ?, session_type = ?, location = ?, instructor = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $session_date,
        $session_time,
        $session_type,
        $location,
        $instructor,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training schedule updated successfully'
    ]);
}

function deleteTrainingSchedule($pdo) {
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM training_schedule WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training schedule deleted successfully'
    ]);
}

function getProgramParticipants($pdo) {
    $program_id = $_GET['program_id'] ?? 0;
    
    if (empty($program_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT tpar.*, u.full_name, u.employee_id, u.email, u.department
        FROM training_participants tpar
        JOIN users u ON tpar.employee_id = u.id
        WHERE tpar.training_program_id = ?
        ORDER BY u.full_name ASC
    ");
    
    $stmt->execute([$program_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'participants' => $participants
    ]);
}

function updateCompletion($pdo) {
    $id = $_POST['id'] ?? 0;
    $completion_percentage = $_POST['completion_percentage'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $completion_percentage = max(0, min(100, intval($completion_percentage)));
    $status = $completion_percentage == 100 ? 'Completed' : ($completion_percentage > 0 ? 'In Progress' : 'Upcoming');
    $completed_at = $completion_percentage == 100 ? date('Y-m-d H:i:s') : null;
    
    $stmt = $pdo->prepare("
        UPDATE training_participants 
        SET completion_percentage = ?, status = ?, completed_at = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $completion_percentage,
        $status,
        $completed_at,
        $id
    ]);
    
    // --- INTEGRATION: AUTO-COMPETENCY UPLIFT ON COMPLETION ---
    if ($status === 'Completed') {
        runCompetencyIntegration($pdo, $id);
    }
    // ---------------------------------------------------------
    
    echo json_encode([
        'success' => true,
        'message' => 'Completion updated successfully',
        'status' => $status
    ]);
}

function finishOrientation($pdo) {
    $program_id = $_POST['program_id'] ?? 0;
    $employee_id = $_SESSION['user_id'] ?? 0;
    
    if (empty($program_id) || empty($employee_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM training_participants WHERE training_program_id = ? AND employee_id = ? ORDER BY enrolled_at DESC LIMIT 1");
    $stmt->execute([$program_id, $employee_id]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participant) {
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this program']);
        return;
    }
    
    $updateStmt = $pdo->prepare("
        UPDATE training_participants
        SET completion_percentage = 100, status = 'Completed', completed_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$participant['id']]);
    
    // --- INTEGRATION: AUTO-COMPETENCY UPLIFT ON COMPLETION ---
    runCompetencyIntegration($pdo, $participant['id']);
    // ---------------------------------------------------------
    
    echo json_encode([
        'success' => true,
        'message' => 'Orientation completed successfully'
    ]);
}

function updateOrientationProgress($pdo) {
    $program_id = $_POST['program_id'] ?? 0;
    $completion_percentage = intval($_POST['completion_percentage'] ?? 55);
    $employee_id = $_SESSION['user_id'] ?? 0;
    
    if (empty($program_id) || empty($employee_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    $completion_percentage = max(0, min(100, $completion_percentage));
    $status = $completion_percentage == 100 ? 'Completed' : ($completion_percentage > 0 ? 'In Progress' : 'Upcoming');
    $completed_at = $completion_percentage == 100 ? date('Y-m-d H:i:s') : null;
    
    $stmt = $pdo->prepare("SELECT id FROM training_participants WHERE training_program_id = ? AND employee_id = ? ORDER BY enrolled_at DESC LIMIT 1");
    $stmt->execute([$program_id, $employee_id]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participant) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this program']);
        return;
    }
    
    $updateStmt = $pdo->prepare("
        UPDATE training_participants
        SET completion_percentage = ?, status = ?, completed_at = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$completion_percentage, $status, $completed_at, $participant['id']]);
    
    // --- INTEGRATION: AUTO-COMPETENCY UPLIFT ON COMPLETION ---
    if ($status === 'Completed') {
        runCompetencyIntegration($pdo, $participant['id']);
    }
    // ---------------------------------------------------------
    
    echo json_encode(['success' => true]);
}

/**
 * Helper function: Execute the competency gap closure
 */
function runCompetencyIntegration($pdo, $participant_id) {
    // 1. Get the employee_id and the program's linked competency_id
    $progInfoStmt = $pdo->prepare("
        SELECT tp.competency_id, tpar.employee_id 
        FROM training_participants tpar
        JOIN training_programs tp ON tpar.training_program_id = tp.id
        WHERE tpar.id = ?
    ");
    $progInfoStmt->execute([$participant_id]);
    $progInfo = $progInfoStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($progInfo && !empty($progInfo['competency_id'])) {
        $competency_id = $progInfo['competency_id'];
        $employee_id = $progInfo['employee_id'];
        
        // 2. See if the employee currently has a gap in this competency
        $empCompStmt = $pdo->prepare("
            SELECT id, level, has_gap 
            FROM employee_competencies 
            WHERE competency_id = ? AND employee_id = ?
        ");
        $empCompStmt->execute([$competency_id, $employee_id]);
        $empComp = $empCompStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empComp && $empComp['has_gap'] == 1) {
            // 3. Upgrade their Level and close the gap
            $matrixStmt = $pdo->prepare("SELECT required_level FROM competency_matrix WHERE id = ?");
            $matrixStmt->execute([$competency_id]);
            $requiredLevel = $matrixStmt->fetchColumn() ?: 'Intermediate';
            
            // Jump to the required level automatically
            $newLevel = $requiredLevel;
            
            $updateCompStmt = $pdo->prepare("
                UPDATE employee_competencies 
                SET level = ?, has_gap = FALSE 
                WHERE id = ?
            ");
            $updateCompStmt->execute([$newLevel, $empComp['id']]);
            
            // 4. Recalculate total gaps for this employee
            recalculateEmployeeGaps($pdo, $employee_id);
        }
    }
}

/**
 * Helper function to recalculate the main gap stats for an employee
 */
function recalculateEmployeeGaps($pdo, $employee_id) {
    $gapStmt = $pdo->prepare("SELECT id FROM competency_gaps WHERE employee_id = ?");
    $gapStmt->execute([$employee_id]);
    $gapRecord = $gapStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$gapRecord) return; // No formal gap tracking set up yet
    
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_required,
            SUM(CASE WHEN has_gap = 0 THEN 1 ELSE 0 END) as total_met
        FROM employee_competencies
        WHERE employee_id = ?
    ");
    $statsStmt->execute([$employee_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    $required = (int)$stats['total_required'];
    $current = (int)$stats['total_met'];
    
    if ($required > 0) {
        $gapPercentage = round((($required - $current) / $required) * 100);
    } else {
        $gapPercentage = 0;
    }
    
    $critsStmt = $pdo->prepare("
        SELECT cm.competency 
        FROM employee_competencies ec
        JOIN competency_matrix cm ON ec.competency_id = cm.id
        WHERE ec.employee_id = ? AND ec.has_gap = 1
        LIMIT 3
    ");
    $critsStmt->execute([$employee_id]);
    $critical_gaps = implode(', ', $critsStmt->fetchAll(PDO::FETCH_COLUMN));
    
    $updateGapsStmt = $pdo->prepare("
        UPDATE competency_gaps
        SET required_competencies = ?, current_competencies = ?, gap_percentage = ?, critical_gaps = ?
        WHERE id = ?
    ");
    $updateGapsStmt->execute([
        $required,
        $current,
        $gapPercentage,
        $critical_gaps,
        $gapRecord['id']
    ]);
}

function createTrainingEntry($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $title = $_POST['title'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $category = $_POST['category'] ?? 'General';
    $description = $_POST['description'] ?? '';
    $is_recommendation = isset($_POST['is_recommendation']) ? (int)$_POST['is_recommendation'] : 0;

    if (empty($title) || empty($start_date)) {
        echo json_encode(['success' => false, 'message' => 'Title and Date are required']);
        return;
    }

    // Handle File Uploads
    $documentPath = '';
    if (isset($_FILES['document']) && $_FILES['document']['name'] !== '') {
        if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Document upload error: ' . getUploadError($_FILES['document']['error'])]);
            return;
        }
        $documentPath = handleFileUpload($_FILES['document'], 'docs');
        if (empty($documentPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded document']);
            return;
        }
    }

    $videoPath = '';
    if (isset($_FILES['video']) && $_FILES['video']['name'] !== '') {
        if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Video upload error: ' . getUploadError($_FILES['video']['error'])]);
            return;
        }
        $videoPath = handleFileUpload($_FILES['video'], 'videos');
        if (empty($videoPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded video']);
            return;
        }
    } elseif (!empty($_POST['video_url'])) {
        $videoPath = $_POST['video_url'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO training_programs (title, category, start_date, description, document_path, video_path, is_recommendation, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Upcoming')
    ");

    try {
        $stmt->execute([
            $title,
            $category,
            $start_date,
            $description,
            $documentPath,
            $videoPath,
            $is_recommendation
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Training created successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleFileUpload($file, $type) {
    $uploadDir = 'uploads/' . $type . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }

    return '';
}

function getHR1TrainingNeeds($pdo) {
    // Get all records from competency_gaps where there is an identification for development
    // JOIN with users to get full employee details
    $stmt = $pdo->prepare("
        SELECT 
            cg.*, 
            u.full_name, 
            u.position, 
            u.department as user_dept
        FROM competency_gaps cg
        JOIN users u ON cg.employee_id = u.id
        WHERE cg.gap_percentage > 0 OR cg.critical_gaps IS NOT NULL
        ORDER BY cg.updated_at DESC
    ");
    $stmt->execute();
    $needs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'needs' => $needs]);
}

function getUploadError($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}
