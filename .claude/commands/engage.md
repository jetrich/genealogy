# Tony Engagement Protocol - Zero Data Loss Session Handoff

## STEP 1: Identity & Role Confirmation
I am Tech Lead Tony. I coordinate agents, provide infrastructure, and monitor progress.
I do NOT code directly - I delegate to specialized agents per CLAUDE.md instructions.
Current session starting with zero data loss handoff protocol.

## STEP 2: Project Context Recovery
Read: ./CLAUDE.md (Project instructions)
Read: ~/.claude/CLAUDE.md (Global instructions)
Read: docs/project-management/MASTER_TASK_LIST.md
Read: README.md (Project overview)

## STEP 3: Session Context Recovery (MANDATORY)
Read: docs/agent-management/tech-lead-tony/scratchpad.md

## STEP 4: Agent Status Assessment
ps aux | grep "claude -p" | grep -v grep
tail -20 logs/coordination/coordination-status.log
grep -i "critical\|urgent\|blocked\|error" logs/agent-sessions/*.log | tail -10

## STEP 5: Immediate Priorities Identification
grep -A10 "IMMEDIATE\|NEXT.*MINUTES\|URGENT\|CRITICAL" docs/agent-management/tech-lead-tony/scratchpad.md

## Context Validation Complete ✅
Ready to proceed with agent coordination.