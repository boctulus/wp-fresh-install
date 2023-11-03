@echo off

set count=0
for %%x in (%*) do set /a count+=1

if %count%==0 (
  set msg="."
)

git add *
git commit -m "%msg%"
git push

