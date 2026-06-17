let mediaRecorder;
let audioChunks = [];
let recordingStartTime;
let timerInterval;
let isRecording = false;

const recordButton = document.getElementById('recordButton');
const statusText = document.getElementById('statusText');
const timerElement = document.getElementById('timer');
const loadingElement = document.getElementById('loading');
const summarySection = document.getElementById('summarySection');
const transcriptElement = document.getElementById('transcript');
const careerRecommendations = document.getElementById('careerRecommendations');

recordButton.addEventListener('click', toggleRecording);

async function toggleRecording() {
    if (!isRecording) {
        await startRecording();
    } else {
        stopRecording();
    }
}

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];

        mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
        };

        mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
            await processAudio(audioBlob);
        };

        mediaRecorder.start();
        isRecording = true;
        recordButton.classList.add('recording');
        recordButton.textContent = '⏹️';
        statusText.textContent = 'กำลังบันทึก... กดอีกครั้งเพื่อหยุด';
        
        recordingStartTime = Date.now();
        startTimer();
        
    } catch (error) {
        console.error('Error accessing microphone:', error);
        alert('ไม่สามารถเข้าถึงไมโครโฟนได้ กรุณาอนุญาตการใช้งานไมโครโฟน');
    }
}

function stopRecording() {
    mediaRecorder.stop();
    mediaRecorder.stream.getTracks().forEach(track => track.stop());
    isRecording = false;
    recordButton.classList.remove('recording');
    recordButton.textContent = '🎙️';
    statusText.textContent = 'กำลังประมวลผล...';
    stopTimer();
}

function startTimer() {
    timerInterval = setInterval(() => {
        const elapsed = Date.now() - recordingStartTime;
        const seconds = Math.floor(elapsed / 1000);
        const minutes = Math.floor(seconds / 60);
        const displaySeconds = seconds % 60;
        timerElement.textContent = 
            `${String(minutes).padStart(2, '0')}:${String(displaySeconds).padStart(2, '0')}`;
    }, 1000);
}

function stopTimer() {
    clearInterval(timerInterval);
}

async function processAudio(audioBlob) {
    loadingElement.style.display = 'block';
    summarySection.style.display = 'none';

    const formData = new FormData();
    formData.append('audio', audioBlob, 'recording.wav');

    try {
        const response = await fetch('api/process-voice-career.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            displayResults(result.data);
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }
    } catch (error) {
        console.error('Error processing audio:', error);
        alert('ไม่สามารถประมวลผลเสียงได้');
    } finally {
        loadingElement.style.display = 'none';
        statusText.textContent = 'กดปุ่มเพื่อเริ่มบันทึกเสียงใหม่';
        timerElement.textContent = '00:00';
    }
}

function displayResults(data) {
    // Display transcript
    transcriptElement.innerHTML = `<p>${data.transcript || 'ไม่สามารถแปลงเสียงเป็นข้อความได้'}</p>`;

    // Display career recommendations
    careerRecommendations.innerHTML = '';
    
    if (data.careers && data.careers.length > 0) {
        data.careers.forEach(career => {
            const careerCard = document.createElement('div');
            careerCard.className = 'career-card';
            
            const subjectTags = career.subjects.map(subject => 
                `<span class="subject-tag">${subject}</span>`
            ).join('');
            
            careerCard.innerHTML = `
                <h3>${career.title}</h3>
                <p>${career.description}</p>
                <div class="subject-tags">${subjectTags}</div>
                <span class="match-score">ความเหมาะสม: ${career.match_score}%</span>
            `;
            
            careerRecommendations.appendChild(careerCard);
        });
    } else {
        careerRecommendations.innerHTML = '<p style="text-align: center; color: #666;">ไม่พบข้อมูลอาชีพที่แนะนำ</p>';
    }

    summarySection.style.display = 'block';
}
