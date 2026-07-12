# IPESS Admission & Registration Workflow Flowcharts

This document details the admissions and registration workflows of the IPESS portal. To make it easy for non-technical stakeholders (such as administrators, board members, and officers) to understand, we have broken down the system into an overall diagram followed by step-by-step descriptions for each individual phase.

---

## 1. Overall System Workflow (The Big Picture)

![HD Admissions Workflow Chart](C:\Users\muhdm\.gemini\antigravity-ide\brain\6d041dbd-a8b8-44db-9d0e-edd764fe6ad0\admissions_workflow_chart_1783594262612.png)

This flowchart shows how an application progresses from initial submission to the final stage of becoming an active student. Each block represents an approval gate that must be completed in order.

```mermaid
graph TD
    A([Applicant Submits Application]) --> B[Stage 1: ICT Officer Verification]
    B -->|Reject / Incomplete| B1[Candidate Re-uploads Documents]
    B1 --> B
    B -->|Verify O'Level & Credentials| C[Stage 2: Referee Reports Check]
    C -->|Referees Submit Reports| D[Stage 3: Departmental Review]
    D -->|HOD assigns Supervisor & approves| E[Stage 4: Faculty Officer Review]
    E -->|Decline / Correction| E1[Send back to Dept/Applicant]
    E1 --> D
    E -->|Approve Faculty Stage| F[Stage 5: Postgraduate School Review]
    F -->|Reject or Request Correction| F1[Send Notification for Edits]
    F1 --> A
    F -->|Grant Final Admission| G[Stage 6: Main ICT Staff Processing]
    G -->|Generate Matric & Student ID| H[Stage 7: Activate Acceptance & Admission Letters]
    H --> I([Completed: Registered Active Student])
    
    style A fill:#f9f9f9,stroke:#333,stroke-width:2px
    style I fill:#6EB533,stroke:#fff,stroke-width:2px,color:#fff
    style G fill:#d4edda,stroke:#28a745,stroke-width:1px
    style B fill:#d1ecf1,stroke:#17a2b8,stroke-width:1px
    style D fill:#d1ecf1,stroke:#17a2b8,stroke-width:1px
    style E fill:#d1ecf1,stroke:#17a2b8,stroke-width:1px
    style F fill:#d1ecf1,stroke:#17a2b8,stroke-width:1px
```

---

## 2. Separate Phase-by-Phase Flowcharts

Below are detailed flowcharts for each individual phase, written in plain language.

### Phase 1: ICT Officer (ICTO) Document Verification
**What happens here in plain language:**  
The ICT Officer checks if the applicant's uploaded documents (like O'Level results, certificates, and passports) are clear, readable, and genuine. If everything looks good, the applicant moves forward. If something is blurry or incorrect, they are sent an email asking them to re-upload.

```mermaid
graph TD
    Start([Applicant Submits Documents]) --> Check[ICT Officer Reviews Uploaded Files]
    Check -->|Valid & Clear| Pass[Mark Verified & Proceed]
    Check -->|Blurry / Incorrect Files| Fail[Add Remarks & Decline Stage]
    Fail --> Edit[Applicant Receives Email & Re-uploads File]
    Edit --> Check
    Pass --> End([Advance to Referee Check])
    
    style Start fill:#f9f9f9,stroke:#333
    style Pass fill:#d4edda,stroke:#28a745
    style Fail fill:#f8d7da,stroke:#dc3545
    style End fill:#6EB533,stroke:#fff,color:#fff
```

---

### Phase 2: Referee Report Verification
**What happens here in plain language:**  
The system automatically sends a secure link to the two referees chosen by the applicant. Referees must click the link, fill out a quick form, and upload their ID cards/passports to confirm they know the candidate.

```mermaid
graph TD
    Start([Documents Verified]) --> SendEmail[System Emails Referees Secure Link]
    SendEmail --> RefReview[Referee opens link, fills form & uploads ID]
    RefReview -->|Referee Submits Successfully| Success[Referee Status: Completed]
    RefReview -->|Referee Declines / Disowns Applicant| Fail[Referee Status: Rejected]
    Success --> CheckTwo{Have both Referees responded?}
    CheckTwo -->|Yes| MoveNext([Advance to Departmental Review])
    CheckTwo -->|No| Wait[Wait or Send Automatic Reminders]
    
    style Start fill:#f9f9f9,stroke:#333
    style Success fill:#d4edda,stroke:#28a745
    style Fail fill:#f8d7da,stroke:#dc3545
    style MoveNext fill:#6EB533,stroke:#fff,color:#fff
```

---

### Phase 3: Departmental Review (Head of Department / HOD)
**What happens here in plain language:**  
The Head of Department (HOD) reviews the candidate's academic qualification history to check if they qualify for the chosen course. Before they can approve the application, they must assign an academic Supervisor from their own department.

```mermaid
graph TD
    Start([Referees Endorsed]) --> HODReview[HOD Reviews Candidate Credentials]
    HODReview --> Choice{Does Candidate Qualify?}
    Choice -->|No| Reject[Decline Application & log reason]
    Choice -->|Yes| Assign[Assign Supervisor from same department]
    Assign --> Approve[HOD Approves & Endorses Stage]
    Approve --> End([Advance to Faculty Review])
    
    style Start fill:#f9f9f9,stroke:#333
    style Approve fill:#d4edda,stroke:#28a745
    style Reject fill:#f8d7da,stroke:#dc3545
    style End fill:#6EB533,stroke:#fff,color:#fff
```

---

### Phase 4: Faculty Officer Review
**What happens here in plain language:**  
The Faculty Officer performs an administrative check to ensure the application conforms to general faculty policies and rules before sending it to the PG School for the final decision.

```mermaid
graph TD
    Start([Approved by HOD]) --> FacReview[Faculty Officer Reviews Dossier]
    FacReview --> Decision{Decision}
    Decision -->|Approve| Pass[Endorse & Approve Faculty Stage]
    Decision -->|Decline| Fail[Decline Stage & add feedback comment]
    Fail --> EndDept([Returned to Department or Applicant])
    Pass --> EndPG([Advance to Postgraduate School Review])
    
    style Start fill:#f9f9f9,stroke:#333
    style Pass fill:#d4edda,stroke:#28a745
    style Fail fill:#f8d7da,stroke:#dc3545
    style EndPG fill:#6EB533,stroke:#fff,color:#fff
```

---

### Phase 5: Postgraduate School (PG School) Board Review
**What happens here in plain language:**  
This is the final academic approval gate. The PG School Board reviews the application, including the reviews of the HOD and Faculty Officer. The board can grant final admission, reject the application, or send it back to the candidate/officers to correct information.

```mermaid
graph TD
    Start([Approved by Faculty]) --> Board[Postgraduate Board Evaluates Candidate]
    Board --> Decision{Board Evaluation Action}
    Decision -->|Grant Admission| Approve[Approve Admission Status]
    Decision -->|Decline Admission| Reject[Decline Admission & Send Email]
    Decision -->|Request Correction| Correct[Request applicant/officer to edit details]
    Approve --> EndICT([Advance to ICT Registration])
    Correct --> NotifyApplicant[Applicant notifies to correct files]
    
    style Start fill:#f9f9f9,stroke:#333
    style Approve fill:#d4edda,stroke:#28a745
    style Reject fill:#f8d7da,stroke:#dc3545
    style Correct fill:#fff3cd,stroke:#ffc107
    style EndICT fill:#6EB533,stroke:#fff,color:#fff
```

---

### Phase 6: Main ICT Staff Processing
**What happens here in plain language:**  
Once the PG Board approves admission, the Main ICT team officially registers the candidate. They generate their unique Matriculation Number and Student ID, and activate their Acceptance and Admission Letters so the student can print them and complete their enrollment.

```mermaid
graph TD
    Start([Admission Granted by PG]) --> ICT[Main ICT Staff Registers Candidate]
    ICT --> GenMatric[Generate Matriculation Number]
    ICT --> GenStuID[Generate Student ID Number]
    GenMatric --> ActivateAccept[Activate Acceptance Letter Access]
    GenStuID --> ActivateAdmit[Activate Admission Letter Access]
    ActivateAccept --> FinalStudent([Student Profile Activated - Process Complete])
    
    style Start fill:#f9f9f9,stroke:#333
    style GenMatric fill:#e2e3e5,stroke:#383d41
    style GenStuID fill:#e2e3e5,stroke:#383d41
    style ActivateAccept fill:#d4edda,stroke:#28a745
    style ActivateAdmit fill:#d4edda,stroke:#28a745
    style FinalStudent fill:#6EB533,stroke:#fff,color:#fff
```
